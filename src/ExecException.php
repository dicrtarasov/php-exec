<?php
namespace dicr\exec;

/**
 * Ошибка выполнения команды
 *
 * @author Igor (Dicr) Tarasov <develop@dicr.org>
 * @version 180626
 */
class ExecException extends \Exception
{
    /** @var string выполняемая команда */
    protected $cmd;

    /**
     * Конструктор
     *
     * @param string $cmd команда
     * @param string $error ошибка
     * @param int $code код ошибки
     * @param \Throwable $prev предыдущая проблема
     * @throws \InvalidArgumentException
     */
    public function __construct(string $cmd, string $error = '', int $code = 0, \Throwable $prev = null)
    {
        if ($cmd == '') {
            throw new \InvalidArgumentException('cmd');
        }

        $this->cmd = $cmd;

        if ($error == '') {
            $last = error_get_last();
            if (! empty($last['message'])) {
                $error = $last['message'];
                error_clear_last();
            } else {
                $error = 'ошибка запуска команды';
            }
        }

        parent::__construct($error, $code, $prev);
    }

    /**
     * Возвращает команду
     *
     * @return string
     */
    public function getCmd()
    {
        return $this->cmd;
    }
}