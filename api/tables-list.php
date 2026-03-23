<?php
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
$db = Database::getInstance();
$tables = $db->fetchAll('SELECT id, name FROM `tables` ORDER BY id');
	$active = $db->fetchAll(
		"SELECT DISTINCT table_id
		 FROM orders
		 WHERE status IN ('pending','preparing','ready')
		    OR (status = 'delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE))"
	);
	$occupiedIds = array_column($active, 'table_id');
	foreach ($tables as &$t) {
		$t['occupied'] = in_array($t['id'], $occupiedIds, true);
	}
	unset($t);
Api::success(['tables' => $tables]);
