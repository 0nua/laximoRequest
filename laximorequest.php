<?php

/**
 * @author Vladimir Prisada
 * @email 0nua@mail.ru
 * @email vladimir-prisada@yandex.ru
 * Class LaximoRequest - обертка над requestOEM библиотекой
 * @method appendGetCatalogInfo() appendGetCatalogInfo() Данные каталога
 * @method appendFindVehicleByVIN() appendFindVehicleByVIN(string $vin) Поиск автомобиля по VIN-номеру
 * @method appendListCategories() appendListCategories(int $vehicle_id, int $category_id) Получаем список категорий запчастей
 * @method appendListUnits() appendListUnits(int $vehicle_id, int $category_id) Список юнитов в категории
 * @method appendListQuickGroup() appendListQuickGroup(int $vehicle_id)
 * @method appendListDetailByUnit() appendListDetailByUnit(int $unit_id) Получаем список агрегатов юнита
 * @method appendListQuickDetail() appendListQuickDetail (int $vehicle_id, int $group_id) Получаем данные группы
 */

class LaximoRequest {

    private $_requestOEM;
    private $_pool = array(); //Пул запросов к сервису Laximo

    private static $_config = [
        'catalog_code' => '',
        'catalog_data' => '',
    ];

    /**
     * Установка параметров каталога, с которым предстоит работа
     * @param $catalog_code
     * @param $catalog_data
     */
    public static function setCatalogData($catalog_code, $catalog_data) {
        self::$_config['catalog_code'] = $catalog_code;
        self::$_config['catalog_data'] = $catalog_data;
    }

    function __construct($ssd = '') {
        //Инициализирум объект запросов к базе данных Laximo
        $this->_requestOEM = new GuayaquilRequestOEM(
            self::$_config['catalog_code'],
            $ssd,
            self::$_config['catalog_data']
        );
    }

    /**
     * Метод формирует сообщение об ошибке на основе ответа сервиса
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
                    $message = "Неверные данные: " . implode(',', $error_params) . '.';
                    break;
                case 'E_CATALOGNOTEXISTS':
                    $message = 'Каталог не зарегистрирован в системе: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_UNKNOWNCOMMAND':
                    $message = 'Команда не известна: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_ACCESSDENIED':
                    $message = 'Доступ к каталогу запрещен: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_NOTSUPPORTED':
                    $message = 'Функция не поддерживается каталогом: ' . implode(',', $error_params) . '.';
                    break;
                case 'E_GROUP_IS_NOT_SEARCHABLE':
                    $message = "Не удалось совершить поиск в связи со слишком большой нагрузкой.";
                    break;
                case 'E_INVALIDREQUEST':
                    $message = "Неверно сформирован запрос к Веб-сервису.";
                    break;
                case 'E_UNEXPECTED_PROBLEM':
                    $message = "Неизвестная ошибка";
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
        //Нет ошибки - возвращаем ответ сервиса
        if (!$this->_requestOEM->error) {
            $response_object = $response ? reset($response) : array();
            return $this->responseObjectWalk($response_object);
        }

        throw new \Exception($this->_getErrorMessage());
    }

    /**
     * Добавляем в пул запрос к сервису
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
     * Выполняем все, что сохранено в пуле и очищаем его
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
        //Нет ошибки - возвращаем ответ сервиса
        $methods = array_keys($this->_pool);
        $this->_pool = array();

        if (!$this->_requestOEM->error) {
            $response_data = array(); //Массив с результатами
            foreach ($response as $key => $response_object) {
                $response_data[$methods[$key]] = $this->responseObjectWalk($response_object);
            }
            return $response_data;
        }

        throw new \Exception($this->_getErrorMessage());
    }

    /**
     * Установить ssd можно только переинициализировав объект.
     * @param $ssd
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
