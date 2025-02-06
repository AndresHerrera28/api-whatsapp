<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $accessToken;
    protected $phoneNumberId;
    protected $version;
    protected $whastsappConnection;
    protected $messageHandle;

    public function __construct()
    {
        $this->apiUrl = 'https://graph.facebook.com/';
        $this->accessToken = env('API_TOKEN');
        $this->phoneNumberId = env('BUSINESS_PHONE');
        $this->version = env('API_VERSION');
        $this->whastsappConnection = new WhatsappConnection;
        $this->messageHandle = new MessageHandler;
    }

    /**
     * Enviar un mensaje de texto a través de la API de WhatsApp.
     */
    public function sendMessage($to, $message, $messageId = null)
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'text' => ['body' => $message],
        ];

        // Si se proporciona un mensaje ID, agregarlo como contexto
        if ($messageId) {
            $data['context'] = ['message_id' => $messageId];
        }

        $this->whastsappConnection->sendToWhatsapp($data);
    }

    /**
     * Marcar un mensaje como leído.
     */
    public function markMessageAsRead($messageId)
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        $this->whastsappConnection->sendToWhatsapp($data);
    }

    public function sendInteractiveButtons($to, $bodyText, $buttons)
    {
        $data = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => [
                    "text" => $bodyText
                ],
                "action" => [
                    "buttons" => $buttons
                ]
            ]
        ];
        $this->whastsappConnection->sendToWhatsapp($data);
    }

    public function sendMediaMessage($to, $type, $mediaUrl, $caption = null)
    {
        $mediaObject = [];

        // Definir el objeto de medios según el tipo
        switch ($type) {
            case 'image':
                $mediaObject['image'] = ['link' => $mediaUrl, 'caption' => $caption];
                break;
            case 'audio':
                $mediaObject['audio'] = ['link' => $mediaUrl];
                break;
            case 'video':
                $mediaObject['video'] = ['link' => $mediaUrl, 'caption' => $caption];
                break;
            case 'document':
                $mediaObject['document'] = [
                    'link' => $mediaUrl,
                    'caption' => $caption,
                    'filename' => 'medpet-file.pdf', // Cambiar el nombre del archivo si es necesario
                ];
                break;
            default:
                throw new \Exception('Not Supported Media Type');
        }

        // Preparar el payload
        $data = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
        ], $mediaObject);

        $this->whastsappConnection->sendToWhatsapp($data);
    }

    public function sebdContactMessage($to, $contact)
    {
        $data =  [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "contacts",
            "contacts" => $contact,
        ];

        $this->whastsappConnection->sendToWhatsapp($data);
        $this->messageHandle->sendWelcomeMenu($to);
    }
}
