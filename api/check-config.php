<?php
require_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');
echo "DB_HOST defined: " . (defined('DB_HOST') ? '<b style="color:green">ANO</b> (' . (DB_HOST ? 'neprázdné' : '<b style="color:red">PRÁZDNÉ</b>') . ')' : '<b style="color:red">NE</b>') . "<br>";
echo "DB_NAME defined: " . (defined('DB_NAME') ? '<b style="color:green">ANO</b> (' . (DB_NAME ? 'neprázdné' : '<b style="color:red">PRÁZDNÉ</b>') . ')' : '<b style="color:red">NE</b>') . "<br>";
echo "DB_USER defined: " . (defined('DB_USER') ? '<b style="color:green">ANO</b> (' . (DB_USER ? 'neprázdné' : '<b style="color:red">PRÁZDNÉ</b>') . ')' : '<b style="color:red">NE</b>') . "<br>";
echo "DB_PASS defined: " . (defined('DB_PASS') ? '<b style="color:green">ANO</b> (' . (DB_PASS ? 'neprázdné' : '<b style="color:red">PRÁZDNÉ</b>') . ')' : '<b style="color:red">NE</b>') . "<br>";
