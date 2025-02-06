<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;


class MessageHandler
{
    protected $whatsappService;
    protected $openAi;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
        $this->openAi = new OpenAiService();
    }

    public function handleIcomingMessage($message, $senderInfo) //Metodo encargado de clasificar los tipos de mensajes a enviar
    {
        if ($message && $message['type'] === 'text') {
            $icomingMessage = strtolower(trim($message['text']['body']));
            if ($this->isGreeting($icomingMessage)) {
                $this->sendWelcomeMessage($message['from'], $message['id'], $senderInfo);
                $this->sendWelcomeMenu($message['from']);
            } elseif (Cache::has("appointmentState.{$message['from']}")) {
                $this->handleAppointmentFlow($message['from'], $icomingMessage);
            } elseif (Cache::has("assistandState.{$message['from']}")) {
                $this->handleAssistandFlow($message['from'], $icomingMessage);
            } else {
                $this->handleMenuOption($message['from'], $icomingMessage);
            }
        } elseif ($message['type'] === 'interactive') {
            $option = $message['interactive']['button_reply']['id'] ?? null;
            $this->handleMenuOption($message['from'], $option);
        }

        $this->whatsappService->markMessageAsRead($message['id']);
    }

    public function isGreeting($message) //Verficar si el mensje recibido es un saludo
    {
        $saludos = ["hola", "hi", "hello", "ola", "buenas tardes", "buenas noches", "buenos dias"];

        // Verificar si el mensaje recibido está en la lista de saludos
        // strtolower convierte el mensaje a minúsculas, y trim elimina espacios al principio y al final
        return in_array($message, $saludos);
    }

    public function getNameSender($senderInfo)
    {
        $fullName = $senderInfo['profile']['name'] ?? null;
        $firstName = null;

        if ($fullName) {
            $nameParts = explode(' ', $fullName);
            $firstName = $nameParts[0];
        }

        return $firstName;
    }

    public function sendWelcomeMessage($to, $messageId, $senderInfo) //Enviar mensja de bienvenida
    {
        $nameSender = $this->getNameSender($senderInfo);
        $welcomeMessage = "Hola " . $nameSender . ", Bienvenido a nuestro servicio de veterinaria online, ¿En que puedo ayudarte hoy?";
        $this->whatsappService->sendMessage($to, $welcomeMessage, $messageId);
    }

    public function sendWelcomeMenu($to) //Menu de botones
    {
        $menuMessage = "Elige una opcion";
        $buttons = [
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_1",
                    "title" => "Agendar"
                ]
            ],
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_2",
                    "title" => "Consultar"
                ]
            ],
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_3",
                    "title" => "Ubicación"
                ]
            ]
        ];

        $this->whatsappService->sendInteractiveButtons($to, $menuMessage, $buttons);
    }

    public function handleMenuOption($to, $option)
    {
        $response = '';
        $estado = true;
        $emergencia = false;
        switch ($option) {
            case 'option_1':
                Cache::put("appointmentState.{$to}", ['step' => 'name'], now()->addMinutes(30));
                $response = 'Por favor ingresa tu nombre.';
                break;
            case 'option_2':
                Cache::put("assistandState.{$to}", ['step' => 'question'], now()->addMinutes(30));
                $response = 'Cuéntanos qué le sucede a tu mascota para que podamos ayudarte.';
                break;
            case 'option_3':
                $response = 'Esta es nuestra ubicación.';
                break;
            case 'option_4':
                $response = '*Genial*, nos encanta que la respuesta otorgada, te ah sido de gran ayuda, no olvides que en caso de emergencia, puedes agendar una cita con nosotros.';
                $estado = false;
                break;
            case 'option_6':
                $response = 'Si esto es una emergencia, te invitamos a llamar a nuestra linea de atención';
                $emergencia = true;
                break;
            default:
                $response = 'Lo siento, no entendí tu selección. Por favor, elige una de las opciones del menú.';
                $estado = false;
        }

        $this->whatsappService->sendMessage($to, $response);

        if ($emergencia == true) {
            $this->sendContact($to);
        }
        if ($estado == false) {
            $this->sendWelcomeMenu($to);
        }
    }

    public function handleAppointmentFlow($to, $message)
    {
        // Obtener el estado actual del usuario
        $state = Cache::get("appointmentState.{$to}");
        $response = '';
        $estado = true;

        // Manejar flujo basado en el paso actual
        switch ($state['step']) {
            case 'name':
                $state['name'] = $message;
                $state['step'] = 'petName';
                $response = 'Gracias. Ahora, ¿cuál es el nombre de tu mascota?';
                break;

            case 'petName':
                $state['petName'] = $message;
                $state['step'] = 'petType';
                $response = '¿Qué tipo de mascota es? (por ejemplo: perro, gato, hurón, etc.)';
                break;

            case 'petType':
                $state['petType'] = $message;
                $state['step'] = 'reason';
                $response = '¿Cuál es el motivo de la consulta?';
                break;

            case 'reason':
                $state['reason'] = $message;
                $response = "Genial, tu cita a sido agendada";
                $estado = false;
                break;
        }

        /// Actualizar el estado del usuario en Cache con un tiempo de expiración
        Cache::put("appointmentState.{$to}", $state, now()->addMinutes(2));
        $this->whatsappService->sendMessage($to, $response);
        if ($estado == false) {
            Cache::forget("appointmentState.{$to}");
            $this->sendWelcomeMenu($to);
        }
    }

    public function handleAssistandFlow($to, $message)
    {
        // Obtener el estado actual del usuario
        $state = Cache::get("assistandState.{$to}");

        $menuMessage = "*La respuesta fue de tu ayuda*";

        $buttons = [
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_4",
                    "title" => "Si, Gracias"
                ]
            ],
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_2",
                    "title" => "Hacer otra pregunta"
                ]
            ],
            [
                "type" => "reply",
                "reply" => [
                    "id" => "option_6",
                    "title" => "Emergencia"
                ]
            ]
        ];

        $response  = '';

        if ($state['step'] === 'question') {
            $response = $this->openAi->getResponse($message);
        }

        Cache::forget("assistantState.{$to}");
        $this->whatsappService->sendMessage($to, $response);
        $this->whatsappService->sendInteractiveButtons($to, $menuMessage, $buttons);
    }

    public function sendContact($to)
    {
        $contact = [
            [
                "addresses" => [
                    [
                        "street" => "123 Calle de las mascotas",
                        "city" => "Barranquilla",
                        "state" => "Atlantico",
                        "zip" => "12345",
                        "country" => "Colombia",
                        "country_code" => "CO",
                        "type" => "WORK"
                    ]
                ],
                "emails" => [
                    [
                        "email" => "vetanimdoc@vetanim.com",
                        "type" => "WORK"
                    ]
                ],
                "name" => [
                    "formatted_name" => "Vitanim Contact",
                    "first_name" => "Vitanim",
                    "last_name" => "Contact",
                    "middle_name" => "",
                    "suffix" => "",
                    "prefix" => ""
                ],
                "org" => [
                    "company" => "VetAnim",
                    "department" => "Atención al Cliente",
                    "title" => "Representante"
                ],
                "phones" => [
                    [
                        "phone" => "+573024587545",
                        "type" => "WORK",
                        "wa_id" => "573024587545"
                    ]

                ],
            ]
        ];

        $this->whatsappService->sebdContactMessage($to, $contact);
    }

    public function sendMedia($to) //Envio de mensajes multimedia
    {
        // Configura el tipo de media, URL y el mensaje (caption)
        // Ejemplo: Descomentar uno de los bloques para probar el tipo de media.

        // **Enviar un audio**
        $mediaUrl = 'https://s3.amazonaws.com/gndx.dev/medpet-audio.aac';
        $caption = 'Bienvenida';
        $type = 'audio';

        // **Enviar una imagen**
        /*  $mediaUrl = 'https://s3.amazonaws.com/gndx.dev/medpet-imagen.png';
        $caption = '¡Esto es una Imagen!';
        $type = 'image'; */

        // **Enviar un video**
        /*  $mediaUrl = 'https://s3.amazonaws.com/gndx.dev/medpet-video.mp4';
        $caption = '¡Esto es un video!';
        $type = 'video'; */

        // **Enviar un documento**
        /*  $mediaUrl = 'https://s3.amazonaws.com/gndx.dev/medpet-file.pdf';
        $caption = '¡Esto es un PDF!';
        $type = 'document'; */

        $this->whatsappService->sendMediaMessage($to, $type, $mediaUrl, $caption);
    }
}
