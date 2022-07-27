<?php
/**
 * Автор: Иван Краузе
 *
 * Дата реализации: 27.07.2022 15:38
 *
 * Дата изменения: 28.07.2022 19:52
 *
 * Содержит класс для работы со списками людей
 */

if (class_exists("PeopleDB")) {
    require_once "db_credits.php";


    /**
     * Класс PeopleList предоставляет методы для поиска людей в БД, представления людей списком (массивом), удаления людей
     *  из БД на основе списка (массива).
     */
    class PeopleList
    {
        // Массив с id людей:
        private array $_people_array = [];

        /**
         * Конструктор класса PeopleList.
         * Входные данные: наименование поля (по которому ведется поиск), значение поля и оператор сравнения.
         * Оператор сравнения используется для поиска по заданному полю с заданным значением.
         * Доступные операторы сравнения:
         * '<' - меньше,
         * '>' - больше,
         * '<>' - не равно.
         * Конструктор использует оператор выборки 'SELECT `id`' в соответствии с введенными данными
         *  и записывает в поле $_people_array полученный список идентификаторов (id).
         */
        public function __construct(string $field, string $value, string $comparison_operator)
        {
            if ($comparison_operator !== '<' && $comparison_operator !== '>' && $comparison_operator !== '<>') {
                throw new RuntimeException( "PeopleList: Для инициализации класса необходимо использовать один из операторов сравнения: \n"
                    . "'<' - меньше \n"
                    . "'>' - больше \n"
                    . "'<>' - не равно \n" );
            }
            $connect = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);
            if ($connect->connect_error) {
                throw new RuntimeException('PeopleList: Ошибка соединения: ' . $connect->connect_error);
            }

            $connect->query("USE `people`;");
            $sql = "SELECT `id` FROM `people` WHERE `$field` $comparison_operator '$value'";

            if ($result = $connect->query($sql)) {
                if ($result->num_rows !== 0) {
                    foreach ($result as $row) {
                        $this->_people_array[] = $row["id"];
                    }
                } else {
                    echo("PeopleList: Не найдено записей, где `$field` $comparison_operator '$value'. \n");
                }
            } else {
                echo("PeopleList: Произошла ошибка при выполнении запроса \"$sql\". \n");
            }

            $connect->close();
        }

        public function getAsPeopleDbArray()
        {
            $peopledb_array = [];
            foreach ($this->_people_array as $people_id) {
                $peopledb_array[] = new PeopleDB($people_id);
            }
            return $peopledb_array;
        }

        public function deletePeople()
        {
            $deletedCount = 0;
            foreach ($this->_people_array as $people_id) {
                if ((new PeopleDB($people_id))->deleteData()) {
                    $deletedCount++;
                }
            }
            echo "Удалено $deletedCount записей! \n";
        }
    }


} else {
    throw new RuntimeException("Ошибка файла PeopleList.php: Класс PeopleDB не найден!" . PHP_EOL);
}