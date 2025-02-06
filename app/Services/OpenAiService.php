<?php

namespace App\Services;

use GuzzleHttp\Client;

class OpenAiService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('OPENAI_API_KEY');
    }

    public function getResponse($message)
    {
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres parte de un servicio de asistencia online y debes comportarte como un veterinario de un comercio llamado "VetAnim". Responde de manera simple, resolviendo preguntas de forma clara y precisa. Si es una emergencia o debe contactarse con nosotros (VetAnim), informa al usuario. No debes generar conversación, solo responder directamente.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $message
                        ]
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ]
            ]);

            $body = json_decode($response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? 'No se pudo obtener respuesta.';
        } catch (\Exception $e) {
            // Maneja cualquier error aquí, como excepciones de red o API
            return 'Error al obtener respuesta: ' . $e->getMessage();
        }
    }
}
