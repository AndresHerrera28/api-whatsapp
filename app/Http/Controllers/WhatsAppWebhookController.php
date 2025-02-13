<?php

namespace App\Http\Controllers;

use App\Services\CryptoService;
use App\Services\MessageHandler;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected $whatsappService;
    protected $messageHandle;
    protected $cryptoService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService;
        $this->messageHandle = new MessageHandler;
        $this->cryptoService = new CryptoService;
    }

    public function verifyWebhook(Request $request) 
    {
        try {
            $verify_token = env('WEBHOOK_VERIFY_TOKEN');
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $token === $verify_token) {
                Log::info("Webhook verificado con éxito.");
                return response($challenge, 200);
            }

            return response()->json(['error' => 'Token inválido'], 403);
        } catch (\Throwable $e) {
            Log::error("Error en la verificación del webhook: " . $e->getMessage());
            return response()->json(['error' => 'Verificación fallida'], 403);
        }
    }

    public function handleWebhook(Request $request) //Punto de conexion para mensajes entrantes
    {
        $data = $request->all();
        $privatePem = env('PRIVATE_KEY');
        $this->cryptoService->decryptRequest($data, $privatePem, 'growconnect');

        $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        $senderInfo = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? null;

        if ($message) {
            $this->messageHandle->handleIcomingMessage($message, $senderInfo);
        }

        return response()->json(['status' => 'success']);
    }
}
