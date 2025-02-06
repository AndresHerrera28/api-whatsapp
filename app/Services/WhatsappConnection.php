<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappConnection {

    protected $apiUrl;
    protected $accessToken;
    protected $phoneNumberId;
    protected $version;

    public function __construct()
    {
        $this->apiUrl = 'https://graph.facebook.com/';
        $this->accessToken = env('API_TOKEN');
        $this->phoneNumberId = env('BUSINESS_PHONE');
        $this->version = env('API_VERSION');
    }

    public function sendToWhatsapp($data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type'  => 'application/json',
            ])->post("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages", $data);
    
            Log::info('Respuesta enviada:', $response->json());
    
            return $response->json();
        } catch (\Throwable $e) {
           Log::error('Error de conexion Motivo: '.$e->getMessage());
        }
    }

}