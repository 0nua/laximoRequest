<?php

/**
 * @author Vladimir Prisada
 * @email vladimir.prisada@gmail.com
 * Class LaximoRequest
 * @method appendGetCatalogInfo() appendGetCatalogInfo()
 * @method appendFindVehicleByVIN() appendFindVehicleByVIN(string $vin)
 * @method appendListCategories() appendListCategories(int $vehicle_id, int $category_id)
 * @method appendListUnits() appendListUnits(int $vehicle_id, int $category_id)
 * @method appendListQuickGroup() appendListQuickGroup(int $vehicle_id)
 * @method appendListDetailByUnit() appendListDetailByUnit(int $unit_id)
 * @method appendListQuickDetail() appendListQuickDetail (int $vehicle_id, int $group_id)
 */

class LaximoRequest {

    private $_requestOEM;
    private $_pool = array();

    private static $_config = [
        'catalog_code' => '',
        'catalog_data' => '',
    ];

    /**
     * @param $catalog_code
     * @param $catalog_data
     */
    public static function setCatalogData($catalog_code, $catalog_data) {
        self::$_config['catalog_code'] = $catalog_code;
        self::$_config['catalog_data'] = $catalog_data;
    }

    function __construct($ssd = '') {
        $this->_requestOEM = new GuayaquilRequestOEM(
            self::$_config['catalog_code'],
            $ssd,
            self::$_config['catalog_data']
        );
    }

    /**
     * @return string
     */
    private function _getErrorMessage() {
        $error = $this->_requestOEM->error;
        if (!$error) {
            return '';
        }

        $error_params = explode(':', $error);
        $message = '';
        if ($error_params) {
            $error_type = array_shift($error_params);
            switch ($error_type) {
                case 'E_INVALIDPARAMETER':
                    $message = "Invalid params: " . implode(',', $error_params) . '.';
                    break;
                case 'E_CATALOGNOTEXISTS':
                    $message = 'Catalog not exists: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_UNKNOWNCOMMAND':
                    $message = 'Unknown command: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_ACCESSDENIED':
                    $message = 'Access denied: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_NOTSUPPORTED':
                    $message = 'Function not supported: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_GROUP_IS_NOT_SEARCHABLE':
                    $message = "Search was failed. Try later...";
                    break;
                case 'E_INVALIDREQUEST':
                    $message = "Invalid request";
                    break;
                case 'E_UNEXPECTED_PROBLEM':
                    $message = "Unexpected error";
                    break;
            }
        }

        return $message;
    }

    private function responseObjectWalk($object) {
        $items = array();
        $counter = 0;
        while (!is_null($object->row[$counter])) {
            $items[] = $object->row[$counter];
            $counter++;
        }

        $counter = 0;
        while (!is_null($object->Category[$counter])) {
            $items[] = $object->Category[$counter];
            $counter++;
        }

        return count($items) == 1 ? reset($items) : $items;
    }

    function __call($name, $args) {
        if (!method_exists($this->_requestOEM, $name)) {
            return array();
        }

        call_user_func_array(array($this->_requestOEM, $name), $args);
        $response = $this->_requestOEM->query();
        if (!$this->_requestOEM->error) {
            $response_object = $response ? reset($response) : array();
            return $this->responseObjectWalk($response_object);
        }

        throw new \Exception($this->_getErrorMessage());
    }

    /**
     * @param $name - имя метода
     * @param array $args - параметры
     * @return bool
     * @throws Exception
     */
    public function push($name, $args = array()) {
        if ($name) {
            $this->_pool[$name] = (array)$args;
            return true;
        }
        throw new \Exception('Operation failed: pool pushing wrong');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function pullQueries() {
        if ($this->_pool) {
            return array();
        }

        foreach ($this->_pool as $name => $args) {
            call_user_func_array(array($this->_requestOEM, $name), $args);
        }

        $response = $this->_requestOEM->query();
        $methods = array_keys($this->_pool);
        $this->_pool = array();

        if (!$this->_requestOEM->error) {
            $response_data = array();
            foreach ($response as $key => $response_object) {
                $response_data[$methods[$key]] = $this->responseObjectWalk($response_object);
            }
            return $response_data;
        }

        throw new \Exception($this->_getErrorMessage());
    }

    /**
     * @throws Exception
     */
    public function setSSD($ssd) {
        if (!$ssd) {
            throw new \Exception('Invalid ssd');
        }

        unset($this->_requestOEM);
        $this->__construct($ssd);
    }

}
