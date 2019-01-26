<?php
namespace dicr\exec;

/**
 * Выполнение команд локально.
 * Использует различные доступные методы.
 *
 * @author Igor (Dicr) Tarasov <develop@dicr.org>
 * @version 180624
 */
class LocalExec implements ExecInterface
{
    /** @var array запрещенные функции */
    protected static $disabledFns;

    /**
     * Возвращает список запрещенных функций.
     *
     * @return array
     */
    protected function getDisabledFns()
    {
        if (! isset(self::$disabledFns)) {
            $disabledList = ini_get('disable_functions') . ',' . ini_get('suhosin.executor.func.blacklist');
            self::$disabledFns = preg_split('~[\s\,]+~uism', $disabledList, - 1, PREG_SPLIT_NO_EMPTY);
        }
        return self::$disabledFns;
    }

    /**
     * Проверяет запрещена ли функция.
     *
     * @param string $func название функции
     * @return boolean
     */
    public function isDisabled(string $func)
    {
        return !function_exists($func) || in_array($func, $this->getDisabledFns());
    }

    /**
     * Создает команду для запуска
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     */
    public function createCommand(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = trim($cmd);
        if ($cmd === '') {
            throw new \InvalidArgumentException('empty cmd');
        }

        $command = escapeshellcmd($cmd);

        if (! empty($args)) {
            if (! empty($opts['escape'])) {
                $args = array_map(function ($arg) {
                    return escapeshellarg($arg);
                }, $args);
            }
            $command .= ' ' . implode(' ', $args);
        }

        return $command;
    }

    /**
     * Выполняет exec
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function exec(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);
        /** @var array $out */
        $out = [];

        /** @var int $return */
        $return = null;

        exec($cmd, $out, $return);

        $out = implode('', $out);
        if (! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }

        return $out;
    }

    /**
     * Выполняет passthru
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function passthru(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);

        ob_start();

        /** @var int $return */
        $return = null;

        passthru($cmd, $return);

        $out = \ob_get_clean();
        if (! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }
        return $out;
    }

    /**
     * Выполняет system
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function system(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);
        ob_start();
        $ret = shell_exec($cmd, $return);
        $out = ob_get_clean();
        if ($ret === false || ! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }
        return $out;
    }

    /**
     * Выполняет shell_exec
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function shellExec(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);
        return shell_exec($cmd);
    }

    /**
     * Выполняет proc_open
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function popen(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);

        $f = @popen($cmd, 'rt');
        if (! $f) {
            throw new ExecException($cmd);
        }

        $out = @stream_get_contents($f);
        if ($out === false) {
            throw new ExecException($cmd);
        }

        if (@pclose($f) === -1) {
            throw new ExecException($cmd);
        }

        return $out;
    }

    /**
     * Выполняет proc_open
     *
     * @param string $cmd комманда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @throws ExecException
     * @return string вывод комманды
     */
    public function procOpen(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = $this->createCommand($cmd, $args, $opts);
        $temp = @fopen('php://temp', 'wt+');

        $proc = @proc_open($cmd, [
            0 => ['file', '/dev/null'],
            1 => $temp,
            2 => $temp
        ]);

        if ($proc === false) {
            throw new ExecException($cmd);
        }

        @rewind($temp);
        $out = stream_get_contents($temp);
        if ($out === false) {
            throw new ExecException($cmd);
        }

        $return = @proc_close($proc);

        if (! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }

        @fclose($temp);

        return $out;
    }

    /**
     * {@inheritdoc}
     * @see ExecInterface::run()
     */
    public function run(string $cmd, array $args = [], array $opts = [])
    {
        $out = null;

        if (! $this->isDisabled('exec')) {
            $out = $this->exec($cmd, $args, $opts);
        } elseif (! $this->isDisabled('system')) {
            $out = $this->system($cmd, $args, $opts);
        } elseif (! $this->isDisabled('passthru')) {
            $out = $this->passthru($cmd, $args, $opts);
        } elseif (! $this->isDisabled('popen')) {
            $out = $this->popen($cmd, $args, $opts);
        } elseif (! $this->isDisabled('proc_open')) {
            $out = $this->procOpen($cmd, $args, $opts);
        } elseif (! $this->isDisabled('shell_exec')) {
            $out = $this->shellExec($cmd, $args, $opts);
        } else {
            throw new ExecException($this->createCommand($cmd, $args, $opts), 'все функции запрещены');
        }

        return $out;
    }
}