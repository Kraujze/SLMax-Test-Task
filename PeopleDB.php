<?php
/**
 * Автор: Иван Краузе
 *
 * Дата реализации: 27.07.2022 15:27
 *
 * Дата изменения: 28.07.2022 19:00
 *
 * Содержит класс для работы с базой данных людей, а также содержащий некоторые статические методы конвертации
 */
require_once "db_credits.php";


/**
 * Класс PeopleDB предоставляет методы для сохранения и удаления информации из базы данных, а также для различных
 *  преобразования данных.
 */
class PeopleDB
{
    // Индентификатор:
    private int $_id;

    // Имя:
    private string $_name;

    // Фамилия:
    private string $_surname;

    // Дата рождения:
    private string $_birth_date;

    // Пол: $_gender = 0 - мужчина; $_gender = 1 - женщина;
    private int $_gender;

    // Город рождения:
    private string $_birth_city;

    /**
     * Конструктор класса PeopleDB.
     * Входные данные: id объекта, опционально: имя, фамилия, дата рождения, пол и город рождения.
     * 1) Если передан только один параметр (id), конструктор загрузит данные из БД в соответствии с переданным id.
     * 2) Если переданы все шесть параметров (id, name, surname, birth_date, gender и birth_city),
     *  конструктор записывает полученные данные в поля, после чего создает в БД запись с этими данными.
     * 3) Если каких-то параметров не хватает для п. (2), конструктор генерирует исключение RuntimeException.
     * Для сценариев (1) и (2) проводится валидация используемых данных.
     */
    public function __construct(string $id, string $name = '', string $surname = '',
        string $birth_date = '', string $gender = '-1', string $birth_city = ''
    ) {
        $errors = [];
        $args_count = func_num_args();
        switch ($args_count) {
            case 1:
                $connect = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);
                if ($connect->connect_error) {
                    throw new \RuntimeException('PeopleDB: Ошибка соединения: ' . $connect->connect_error);
                }

                // Валидация `id`: только целые числа
                if (preg_match("/\D/", $id)) {
                    $errors[] = "Ошибка валидации: значение `id` должен быть целочисленного вида;\n";
                    break;
                }

                $connect->query("USE `people`;");
                $sql = "SELECT * FROM `people` WHERE `id` = $id";

                if ($result = $connect->query($sql)) {
                    if ($result->num_rows !== 0) {
                        $result = $result->fetch_assoc();
                        $this->_id = $result['id'];
                        $this->_name = $result['name'];
                        $this->_surname = $result['surname'];
                        $this->_birth_date = $result['birth_date'];
                        $this->_gender = $result['gender'];
                        $this->_birth_city = $result['birth_city'];
                    } else {
                        echo("PeopleDB: Не найдено записей, где id = $id. \n");
                    }
                } else {
                    echo("PeopleDB: Произошла ошибка при выполнении запроса \"$sql\". \n");
                }

                $connect->close();
                break;

            case 6:
                // Валидация `id`: только целые числа
                if (preg_match("/\D/", $id)) {
                    $errors[] = "Ошибка валидации: значение `id` должен быть целочисленного вида;\n";
                }

                // Валидация `name`: кириллица и латиница
                if (preg_match("/[^а-яА-Яa-zA-Z]/u", $name)) {
                    $errors[] = "Ошибка валидации: значение `name` должно содержать только буквы;\n";
                }

                // Валидация `surname`: кириллица и латиница
                if (preg_match("/[^а-яА-Яa-zA-Z]/u", $surname)) {
                    $errors[] = "Ошибка валидации: значение `surname` должно содержать только буквы;\n";
                }

                // Валидация `birth_date`: формат yyyy-mm-dd (числа), 'mm' не больше 12, 'dd' не больше 31
                if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $birth_date)) {
                    $errors[] = "Ошибка валидации: значение `birth_date` должно быть формата yyyy-mm-dd;\n";
                } else {
                    // Проверка на корректное значение месяца и дня
                    try {
                        new DateTime($birth_date);
                    } catch (Throwable $e) {
                        $errors[] = "Ошибка валидации: некорректное значение даты `birth_date`, "
                                  . "проверьте месяц (макс. 12) и день (макс. 31);\n";
                    }
                }

                // Валидация `gender`: значение 0 или 1
                if ($gender !== "0" && $gender !== "1") {
                    $errors[] = "Ошибка валидации: значение `gender` должно быть 0 или 1;\n";
                }

                // Валидация `birth_city`: кириллица и латиница, символы '.' и '-'
                if (preg_match("/[^а-яА-Яa-zA-Z.-]/u", $birth_city)) {
                    $errors[] = "Ошибка валидации: значение `birth_city` должно содержать только буквы;\n";
                }

                if (count($errors) === 0) {
                    $this->_id = $id;
                    $this->_name = $name;
                    $this->_surname = $surname;
                    $this->_birth_date = $birth_date;
                    $this->_gender = $gender;
                    $this->_birth_city = $birth_city;

                    $this->saveData();
                }

                break;

            default:
                throw new Exception(  'PeopleDB: Ошибка: для инициализации класса необходимо указать '
                                   . 'либо только параметр $id для получения информации из БД, '
                                   . 'либо все параметры для создания записи в БД' );
        }

        if (count($errors) !== 0) {
            $full_error = '';
            foreach ($errors as $err) {
                $full_error .= $err;
            }
            throw new RuntimeException("Ошибка инициализации PeopleDB: \n" . $full_error);
        }
    }

    public function deleteData()
    {
        try {
            $connect = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);
            if ($connect->connect_error) {
                throw new RuntimeException('PeopleDB: Ошибка соединения: ' . $connect->connect_error);
            }

            $connect->query("USE `people`;");
            $sql = "DELETE FROM `people` WHERE `id` = $this->_id;";

            if (!($result = $connect->query($sql))) {
                echo "PeopleDB: Произошла ошибка при выполнении запроса \"$sql\". \n";
            }

            $connect->close();
            return $result;
        } catch (Throwable $e) {
            echo "Ошибка PeopleDB: " . $e->getMessage();
        }
        return false;
    }

    public function saveData()
    {
        $connect = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);

        if ($connect->connect_error) {
            throw new RuntimeException('PeopleDB: Ошибка соединения: ' . $connect->connect_error);
        }

        $connect->query("USE `people`;");
        $sql = "INSERT INTO `people` (`id`, `name`, `surname`, `birth_date`, `gender`, `birth_city`) "
            . "VALUES ($this->_id, '$this->_name', '$this->_surname', '$this->_birth_date', $this->_gender, '$this->_birth_city');";
        $connect->query($sql);

        $connect->close();
    }

    public static function convertAge(string $birth_date)
    {
        return (new DateTime($birth_date))->diff(new DateTime())->y;
    }

    public static function convertGender(int $gender)
    {
        return $gender === 0 ? 'муж' : 'жен';
    }

    /**
     * Метод для получения экземпляра stdClass, содержащего поля данного экземпляра PeopleDB,
     *  а также (опционально) поля конвертированных `birth_date` и `gender` как `age` и `gender_str` .
     * $format_age = true - включить в генерируемый экземпляр stdClass поле `age`.
     * $format_gender = true - включить в генерируемый экземпляр stdClass поле `gender_str`.
     */
    public function getFormatted(bool $format_age = false, bool $format_gender = false)
    {
        $formatted = new stdClass;

        $formatted->_id = $this->_id;
        $formatted->_name = $this->_name;
        $formatted->_surname = $this->_surname;
        $formatted->_birth_date = $this->_birth_date;
        $formatted->_gender = $this->_gender;
        $formatted->_birth_city = $this->_birth_city;

        if ($format_age) {
            $formatted->age = self::convertAge($formatted->_birth_date);
        }

        if ($format_gender) {
            $formatted->gender_str = self::convertGender($formatted->_gender);
        }

        return $formatted;
    }

}