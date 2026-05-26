<?php
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'version' => '0.1.0', 'time' => date('c')]);
