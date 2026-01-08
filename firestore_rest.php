<?php

/* ==============================
   BASE64 URL ENCODE
============================== */
function base64url_encode($data)
{
    return rtrim(
        strtr(base64_encode($data), '+/', '-_'),
        '='
    );
}

/* ==============================
   GET OAUTH ACCESS TOKEN
============================== */
function getAccessToken()
{
    $key = json_decode(
        file_get_contents(__DIR__ . '/serviceAccountKey.json'),
        true
    );

    $header = base64url_encode(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]));

    $time = time();

    $payload = base64url_encode(json_encode([
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $time,
        'exp'   => $time + 3600
    ]));

    openssl_sign(
        "$header.$payload",
        $signature,
        $key['private_key'],
        'SHA256'
    );

    $jwt = "$header.$payload." . base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt
        ])
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($response['access_token'])) {
        die('Failed to get access token: ' . json_encode($response));
    }

    return $response['access_token'];
}

/* ==============================
   INSERT / UPDATE DOCUMENT
============================== */
function firestoreInsert($collection, $docId, $data)
{
    $projectId = 'nyam-nyam-77a54'; // ✅ YOUR PROJECT ID
    $token     = getAccessToken();

    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/$collection/$docId";

    $fields = [];

    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_int($value)) {
            $fields[$key] = ['integerValue' => $value];
        } elseif (is_float($value)) {
            $fields[$key] = ['doubleValue' => $value];
        }
    }

    $payload = json_encode([
        'fields' => $fields
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => $payload
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/* ==============================
   GET DOCUMENT BY FIELD (LOGIN)
============================== */
function firestoreGetByField($collection, $field, $value)
{
    $projectId = 'nyam-nyam-77a54';
    $token     = getAccessToken();

    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents:runQuery";

    $query = [
        'structuredQuery' => [
            'from' => [
                ['collectionId' => $collection]
            ],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $field],
                    'op'    => 'EQUAL',
                    'value' => ['stringValue' => $value]
                ]
            ],
            'limit' => 1
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($query)
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($response[0]['document'])) {
        return null;
    }

    return $response[0]['document'];
}

