<?php
/**
*   Very simple chat bot @verysimple_bot by Novelsite.ru
*   05.07.2021
*/
header('Content-Type: text/html; charset=utf-8');

$bot_token = '603*******:AEE-oFXWjr8M1JdIr3hmPPLmb-oWVuA06fw'; // токен вашего бота
$data = file_get_contents('php://input'); // весь ввод перенаправляем в $data
$data = json_decode($data, true); // декодируем json-закодированные-текстовые данные в PHP-массив

// Для отладки, добавим запись полученных декодированных данных в файл message.txt
// file_put_contents('message.txt', print_r($data, true));

// Устанавливаем доступы к базе данных:
$hostname = 'localhost';
$username = 'host1857549_bot';
$password = 'root';
$db_name = 'host1857549_bot';

try {
    // Подключаемся к серверу
    $pdo = new PDO("mysql:host=$hostname;dbname=$db_name", $username, $password);
    // SQL-выражение для создания таблицы
    $sql = "CREATE TABLE IF NOT EXISTS `users` (`id` INT AUTO_INCREMENT PRIMARY KEY, `telegram_id` BIGINT NOT NULL UNIQUE, `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    // Выполняем SQL-выражение
    $pdo->exec($sql);
} catch (PDOException $e) {
    file_put_contents('error.txt', $e->getMessage() . "\n", FILE_APPEND);
}

// Получаем сообщение, которое юзер отправил боту и заполняем переменные для дальнейшего использования
$message = $data['message']['text'];
if (! empty($message) || $message == 0) {
    $chat_id = $data['message']['from']['id'];
    // $user_name = $data['message']['from']['username'];
    $first_name = $data['message']['from']['first_name'];
    // $last_name = $data['message']['from']['last_name'];
    $text = trim($message);

    // Если пришла одна из поддерживаемых ботом команд
    isCommand($bot_token, $chat_id, $first_name, $text);

    // Пробуем найти в БД пользователя по его chat_id
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute(['telegram_id' => $chat_id]);

    // Если такого пользователя нет в базе, добавляем его с балансом 0
    if (! $stmt->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (telegram_id, balance) VALUES (:telegram_id, 0.00)");
            $stmt->execute(['telegram_id' => $chat_id]);
            $pdo->commit();
            sendMessage($bot_token, $chat_id, "Поздравляем! Успешно создана учётная запись с $0.00 на Вашем счёте.");
        } catch (Exception $e) {
            $pdo->rollBack();
            sendMessage($bot_token, $chat_id, "Не удалось создать Вашу учётную запись. Попробуйте снова.");
            file_put_contents('error.txt', $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    // Иначе проверяем, является ли сообщение числом (с учетом запятой и точки)
    elseif (is_numeric(str_replace([',', '.'], '', $text))) {
        // Получаем текущий баланс пользователя
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE telegram_id = :telegram_id");
        $stmt->execute(['telegram_id' => $chat_id]);
        $user_balance = $stmt->fetchColumn();

        // Обрабатываем число, которое отправил пользователь: заменяем запятую на точку, если нужно
        $amount = str_replace([','], '.', $text);
        // Получаем и округляем итоговую сумму до двух знаков после запятой
        $new_balance = round(($user_balance + (float)$amount), 2);

        // Если пользователь прислал 0 в сообщении, просто покажем ему текущий баланс
        if ($amount == 0) {
            sendMessage($bot_token, $chat_id, "Ваш баланс: $$user_balance");
        }
        // Если новый баланс отрицательный, показываем ошибку
        elseif ($new_balance < 0) {
            sendMessage($bot_token, $chat_id, "Ошибка: на вашем счёте недостаточно средств.");
        }
        // Если новый баланс равен или превышает $100000000, показываем предупреждение
        elseif ($new_balance >= 100000000) {
            sendMessage($bot_token, $chat_id, "Сумма не зачислена. К сожалению, наш банк не может обслуживать счёт с суммой от $100000000.");
        }
        // Иначе обновляем баланс пользователя
        else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE users SET balance = :balance WHERE telegram_id = :telegram_id");
                $stmt->execute(['balance' => $new_balance, 'telegram_id' => $chat_id]);
                $pdo->commit();
                sendMessage($bot_token, $chat_id, "Ваш новый баланс: $$new_balance");
            } catch (Exception $e) {
                $pdo->rollBack();
                sendMessage($bot_token, $chat_id, "Произошла ошибка. Попробуйте снова.");
                file_put_contents('error.txt', $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    } else {
        sendMessage($bot_token, $chat_id, "Пожалуйста, отправьте число для изменения баланса.");
    }
}

// Функция проверки, является ли сообщение от юзера поддерживаемой ботом командой
function isCommand($bot_token, $chat_id, $first_name, $text) {
    $text_return = "";
    if ($text == '/start') {
        $text_return = "Добро пожаловать, $first_name!
Чтобы изменить баланс своего счёта, пришлите число.
Если число положительное, то сумма зачислится на Ваш счёт.
Если число отрицательное, то сумма спишется с Вашего счёта.
* На счету не может быть отрицательной суммы.
";
    }
    elseif ($text == '/help') {
        $text_return = "Привет, $first_name, вот команды, что я понимаю: 
/start - начало работы
/help - список команд
/about - о нас
";
    }
    elseif ($text == '/about') {
        $text_return = "Makarenkov_bot:
Я пример самого простого бота для телеграм, написанного на простом PHP.
Мой код можно скачивать, дополнять, исправлять. Код доступен в этой статье:
https://www.novelsite.ru/kak-sozdat-prostogo-bota-dlya-telegram-na-php.html
";
    }

    if ($text_return) {
        sendMessage($bot_token, $chat_id, $text_return);
    }
}

// Функция отправки сообщения от бота в диалог с юзером
function sendMessage($bot_token, $chat_id, $text, $reply_markup = '')
{
    $ch = curl_init();
    $ch_post = [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $bot_token . '/sendMessage',
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id,
            'parse_mode' => 'HTML',
            'text' => $text,
            'reply_markup' => $reply_markup,
        ]
    ];

    curl_setopt_array($ch, $ch_post);
    curl_exec($ch);
}
