<?php 
namespace dicr\exec;

/**
 * Выполняет команды
 * 
 * @author Igor (Dicr) Tarasov <develop@dicr.org>
 * @version 180626
 */
interface Exec {
	
	/**
	 * Выполняет внешнюю команду.
	 * 
	 * @param string $cmd команда
	 * @param array $args аргументы
	 * @param array $options опции функции
	 * - escape bool - выполнить экранирование аргументов, false
	 * @throws \dicr\exec\ExecException
	 * @return string вывод команды
	 */
	public function run(string $cmd, array $args=[], array $options=[]);
}