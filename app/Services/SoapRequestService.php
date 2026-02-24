<?php

namespace App\Services;

use App\DTO\TrainPathRequestDTO;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use SoapClient;
use SoapFault;
use SoapVar;

class SoapRequestService
{
    private array $responseData = [];

    private SoapVar $requestData;

    public function __construct(private ?SoapClient $client = null) { }

    public function fetchSoap(TrainPathRequestDTO $trainPathRequestDTO): void
    {
        $this->initSoapClient();
        $this->prepareRequestData($trainPathRequestDTO);
        $this->sendRequest();
        $this->validateResponse();
    }

    public function getResponse(): array
    {
        return $this->responseData;
    }

    private function initSoapClient(): void
    {
        if ($this->client !== null) {
            Log::channel('soap')->debug('soap.init.skipped', [
                'reason' => 'SOAP Клиент уже инициализирован',
            ]);

            return;
        }

        Log::channel('soap')->info('soap.init.started');

        $clientOptions = [
            'exceptions' => true,
            'connection_timeout' => 30,
            'trace' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
        ];

        try {
            $this->client = new SoapClient(config('services.soap.wsdl'), $clientOptions);

            // URL WSDL не логируем т.к. может содержать чувствительные данные
            Log::channel('soap')->info('soap.init.completed');
        } catch (SoapFault $e) {
            Log::channel('soap')->error('soap.init.failed', [
                'fault_code' => $e->faultcode,
                'fault_message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Ошибка инициализации SOAP-клиента: ' . $e->getMessage());
        }
    }

    /**
     * По-хорошему вместо этого метода надо использовать абстракцию сервиса,
     * который будет выполнять маппинг (конфиг, БД, внешний soap запрос и тд.).
     *
     * В рамках тестового задания просто используем это как заглушку маппинга.
     *
     * Маппинг названия станции в код SOAP-сервиса.
     * @throws InvalidArgumentException если станция не найдена в маппинге.
     */
    private function stationMapper(string $stationName): int
    {
        $stationMap = [
            'москва' => 2000000,
            'санкт-петербург' => 2004000,
        ];

        if (! array_key_exists($stationName, $stationMap)) {
            $errorMsg = "Станция \"{$stationName}\" не найдена в списке станций.";

            Log::channel('soap')->error('soap.request.prepared', [
                'error_message' => $errorMsg,
            ]);

            throw new InvalidArgumentException($errorMsg);
        }

        return $stationMap[$stationName];
    }

    private function prepareRequestData(TrainPathRequestDTO $trainPathRequestDTO): void
    {
        Log::channel('soap')->debug('soap.request.prepared', [
            'train' => $trainPathRequestDTO->train_number,
            'from_code' => $this->stationMapper($trainPathRequestDTO->departure_station),
            'to_code' => $this->stationMapper($trainPathRequestDTO->arrival_station),
            'day' => $trainPathRequestDTO->day,
            'month' => $trainPathRequestDTO->month,
        ]);

        $xml =
            '<auth>'.
                '<login>' . config('services.soap.login') . '</login>'.
                '<psw>' . config('services.soap.password') . '</psw>'.
                '<terminal>' . config('services.soap.terminal') . '</terminal>'.
                '<represent_id>' . config('services.soap.represent_id') . '</represent_id>'.
                '<language>' . config('services.soap.language', 'RU') . '</language>'.
                '<currency>' . config('services.soap.currency', 'RUB') . '</currency>'.
            '</auth>'.
            '<train>' . $trainPathRequestDTO->train_number . '</train>'.
            '<travel_info>'.
                '<from>' . $this->stationMapper($trainPathRequestDTO->departure_station) . '</from>'.
                '<to>' . $this->stationMapper($trainPathRequestDTO->arrival_station) . '</to>'.
                '<day>' . $trainPathRequestDTO->day . '</day>'.
                '<month>' . $trainPathRequestDTO->month . '</month>'.
            '</travel_info>'
        ;

        $this->requestData = new SoapVar($xml, XSD_ANYXML);
    }

    private function sendRequest(): void
    {
        if ($this->client === null) {
            $errorMsg = 'SOAP-клиент не инициализирован.';

            Log::channel('soap')->error('soap.request.sendingError', [
                'error_message' => $errorMsg,
            ]);

            throw new RuntimeException($errorMsg);
        }

        Log::channel('soap')->info('soap.request.sending');

        try {
            $rawResponse = $this->client->trainRoute($this->requestData);
            $this->responseData = json_decode(json_encode($rawResponse), true) ?? [];

            Log::channel('soap')->info('soap.response.received', [
                // Логируем только верхнеуровневые ключи, не будем раздувать лог-файл (на проде бы логировали нормально).
                'response_keys' => array_keys($this->responseData),
            ]);
        } catch (SoapFault $e) {
            Log::channel('soap')->error('soap.request.fault', [
                'fault_code' => $e->faultcode,
                'fault_message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Ошибка SOAP при отправке запроса: ' . $e->getMessage());
        }
    }

    /**
     * @throws RuntimeException если ответ пустой или не содержит обязательных полей
     */
    private function validateResponse(): void
    {
        if (empty($this->responseData)) {
            Log::channel('soap')->error('soap.response.emptyData');

            throw new RuntimeException('Ошибка SOAP ответа: сервис не вернул данные.');
        }

        if (! array_key_exists('train_description', $this->responseData)) {
            Log::channel('soap')->error('soap.response.missing_key', [
                'missing_key' => 'train_description',
                'received_keys' => array_keys($this->responseData),
            ]);

            throw new RuntimeException('Ошибка SOAP ответа: нет данных описания поезда (train_description).');
        }

        if (! array_key_exists('route_list', $this->responseData)) {
            Log::channel('soap')->error('soap.response.missing_key', [
                'missing_key' => 'route_list',
                'received_keys' => array_keys($this->responseData),
            ]);

            throw new RuntimeException('Ошибка SOAP ответа: нет данных маршрута (route_list).');
        }

        Log::channel('soap')->info('soap.response.validated', [
            'train_number' => $this->responseData['train_description']['number'] ?? 'empty',
        ]);
    }
}
