<?php
/**
 * @date: 09.11.2022
 * @author: ASt
 * @desc: Класс для учета онлайн пользователей (авторизация не важна)
 *
 * Init: OnlineUsers::instance()
 *     ->setSessionId()
 *     ->setDownTime(int SECONDS)
 *     ->setLogFile(string PATH)
 *     ->...;
 *
 * Auto Cleaning:      ...->autoClean(): bool;
 * Update User Action: ...->logVisit(): bool;
 * Count of Online:    ...->count(): int;
 * Drop log file:      ...->drop(): bool;
 * Is User Online:     ...->check(string 'session_id'): bool;
 */

namespace Ast\Online;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class Online {

    # Сколько секунд простоя считаются авто логаутом
    private $down_time = 600;

    # Файл для хранения учета в хранилище
    private $log_file  = 'online.txt';

    # ID сессии текущего пользователя
    private $session_id;


    # Singleton
    use Singleton;


    /**
     * Получить ID сессии
     *
     * @return $this
     */
    public function setSessionId(): Online
    {
        $this->session_id = Session::getId() ?: null;

        return $this;
    }

    /**
     * Установить время простоя
     *
     * @param int $seconds
     * @return $this
     */
    public function setDownTime(int $seconds): Online
    {
        $this->down_time = $seconds;

        return $this;
    }

    /**
     * Задать путь для хранения учета
     *
     * @param string $name
     * @return $this
     */
    public function setLogFile(string $name): Online
    {
        $this->log_file = $name;

        return $this;
    }

    /**
     * Кол-во онлайн юзеров
     *
     * @return int
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function count(): int
    {
        # Прочитаем лог
        $data = $this->readLog();

        # Если файла учета онлайна не существует, то никого в онлайне нет
        if ($data === null) {
            return 0;
        }

        # Объявим счетчик
        $count = 0;

        # Поищем, есть ли в логе записи
        foreach ($data as $log) {

            # Если лимит простоя не превышен, то накручиваем счетчик
            if (!$this->downtimeExceeded($log['last_visit'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Проверить онлайн статус пользователя по ID сессии
     *
     * @param string $session_id
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function check(string $session_id): bool
    {
        # Прочитаем лог
        $data = $this->readLog();

        # Если файла учета онлайна не существует, то никого в онлайне нет
        if ($data === null) {
            return false;
        }

        # Поищем, есть ли в нем текущий пользователь
        foreach ($data as $log) {

            # Пользователь найден
            if ($log['id'] == $session_id) {

                # Если лимит простоя превышен, то он разавториован
                if ($this->downtimeExceeded($log['last_visit'])) {
                    return false;
                }

                # Иначе он считается онлайн
                return true;
            }
        }

        # Пользователь не найден - он оффлайн
        return false;
    }

    /**
     * Учет пользователей онлайн
     *
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function logVisit(): bool
    {
        # Если сессия не стартовала, нечего логировать
        if ($this->session_id === null) {
            return false;
        }

        # Прочитаем лог
        $data = $this->readLog();

        # Если файла учета онлайна нет, создадим и добавим данные о текущем пользователе
        if ($data === null) {
            Storage::disk('local')->put($this->log_file, json_encode([$this->defaultData()]));
        }

        # Если файл учета онлайна существует
        else {

            # Поищем, есть ли в нем текущий пользователь
            foreach ($data as $key => $log) {
                if ($log['session_id'] == $this->session_id) {

                    # Пользователь найден, обновим время
                    $data[$key]['last_visit'] = time();
                    Storage::disk('local')->put($this->log_file, json_encode($data));
                    return true;
                }
            }

            # Поиск завершен, такого пользователя в логе нет, добавим его
            $data[] = $this->defaultData();
            Storage::disk('local')->put($this->log_file, json_encode($data));
        }

        return true;
    }

    /**
     * Принудительно удалить файл учета
     *
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function drop(): bool
    {
        # Прочитаем лог
        $data = $this->readLog();

        # Если файла учета онлайна не существует, то удалят нечего
        if ($data === null) {
            return false;
        }

        Storage::disk('local')->delete($this->log_file);

        return true;
    }

    /**
     * Авто чистка файла от неактивных пользователей
     *
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function autoClean(): bool
    {
        # Прочитаем лог
        $data = $this->readLog();

        # Если файла учета онлайна не существует, то чистить нечего
        if ($data === null) {
            return false;
        }

        # Объявим очищенный список
        $cleanArr = [];

        # Поищем, есть ли в логе записи
        foreach ($data as $log) {

            # Если лимит простоя не превышен, то сохраняем пользователя, иначе удаляем
            if (!$this->downtimeExceeded($log['last_visit'])) {
                $cleanArr[] = $log;
            }
        }

        # Если есть хоть 1 пользователь онлайн, обновим лог файл учета
        if (count($cleanArr) > 0) {
            Storage::disk('local')->put($this->log_file, json_encode($cleanArr));
        }

        # Если нет, то файл можно удалить
        else {
            Storage::disk('local')->delete($this->log_file);
        }

        return true;
    }


    /**
     * Если можно прочитать файл, прочитаем
     *
     * @return array|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function readLog():? array
    {
        if (Storage::disk('local')->exists($this->log_file)) {
            return json_decode(Storage::disk('local')->get($this->log_file), true);
        }

        return null;
    }

    /**
     * Данные для лога по умолчанию
     *
     * @return array
     */
    private function defaultData(): array
    {
        return [

            'session_id' => $this->session_id,
            'last_visit' => time()
        ];
    }

    /**
     * Превышен ли лимит простоя сессии
     *
     * @param int $last_visit
     * @return bool
     */
    private function downtimeExceeded(int $last_visit): bool
    {
        return (time() - $last_visit) >= $this->down_time;
    }
}
