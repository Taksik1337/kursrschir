<?php
session_start();

$host = getenv('DOCKER_ENV') ? 'sync_backend' : '127.0.0.1';
$api_url = "http://$host:8000/api";

function call_api($method, $url, $data = false, $token = null) {
    $curl = curl_init();
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_VERBOSE => true // Включаем подробный отчет
    ];

    if ($method == "POST") {
        $options[CURLOPT_POST] = true;
        if ($data) $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);

    if ($response === false) {
        die("Ошибка cURL: " . curl_error($curl) . " | Пытался соединиться с: $url");
    }

    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return ['code' => $http_code, 'body' => json_decode($response, true)];
}
?>