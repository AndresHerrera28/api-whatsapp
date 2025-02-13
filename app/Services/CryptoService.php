<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class CryptoService
{
    public static function decryptRequest(array $body, string $privatePem, string $passphrase)
    {
        $encryptedAesKey = $body['encrypted_aes_key'];
        $encryptedFlowData = $body['encrypted_flow_data'];
        $initialVector = $body['initial_vector'];

        // Obtener clave privada
        $privateKey = openssl_pkey_get_private($privatePem, $passphrase);
        if (!$privateKey) {
            return self::throwException(421, "Fallo al cargar la llave privada.");
        }

        // Desencriptar clave AES con PKCS1_OAEP y SHA-256
        $decryptedAesKey = '';
        if (!openssl_private_decrypt(
            $encryptedAesKey,
            $decryptedAesKey,
            $privateKey,
            OPENSSL_PKCS1_OAEP_PADDING
        )) {
            Log::error("Failed to decrypt AES key.");
            return self::throwException(421, "Failed to decrypt the request. Please verify your private key.");
        }

        // Separar los datos encriptados y el tag de autenticación
        $tagLength = 16;
        $encryptedFlowDataBody = substr($encryptedFlowData, 0, -$tagLength);
        $encryptedFlowDataTag = substr($encryptedFlowData, -$tagLength);

        // Desencriptar los datos con AES-128-GCM
        $decryptedJSONString = openssl_decrypt(
            $encryptedFlowDataBody,
            'aes-128-gcm',
            $decryptedAesKey,
            OPENSSL_RAW_DATA,
            $initialVector,
            $encryptedFlowDataTag
        );

        if ($decryptedJSONString === false) {
            return self::throwException(500, "Decryption failed.");
        }

        return [
            'decryptedBody' => json_decode($decryptedJSONString, true),
            'aesKeyBuffer' => $decryptedAesKey,
            'initialVectorBuffer' => $initialVector,
        ];
    }

    public static function encryptResponse(array $response, string $aesKeyBuffer, string $initialVectorBuffer)
    {
        // Invertir los bytes del IV (XOR con 0xFF)
        $flippedIv = $initialVectorBuffer ^ str_repeat("\xFF", strlen($initialVectorBuffer));

        // Cifrar la respuesta con AES-128-GCM
        $tag = '';
        $encryptedResponse = openssl_encrypt(
            json_encode($response),
            'aes-128-gcm',
            $aesKeyBuffer,
            OPENSSL_RAW_DATA,
            $flippedIv,
            $tag,
            16 // Tamaño del tag
        );

        return base64_encode($encryptedResponse . $tag);
    }

    private static function throwException(int $statusCode, string $message)
    {
        Log::error("FlowEncryptionHelper Error [$statusCode]: $message");
        throw new Exception($message, $statusCode);
    }
}