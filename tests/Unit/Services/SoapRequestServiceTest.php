<?php

namespace Tests\Unit\Services;

use App\DTO\TrainPathRequestDTO;
use App\Services\SoapRequestService;
use InvalidArgumentException;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Tests\TestCase;

class SoapRequestServiceTest extends TestCase
{
    private function makeValidDto(
        string $trainNumber = '016А',
        string $departure = 'москва',
        string $arrival = 'санкт-петербург',
        int $day = 15,
        int $month = 3,
    ): TrainPathRequestDTO {
        return new TrainPathRequestDTO($trainNumber, $departure, $arrival, $day, $month);
    }

    private function buildSoapResponse(): object
    {
        $response = new stdClass();
        $response->train_description = new stdClass();
        $response->train_description->number = '016А';
        $response->route_list = new stdClass();
        $response->route_list->name = 'МОСКВА - САНКТ-ПЕТЕРБУРГ';

        return $response;
    }

    private function makeFakeSoap(mixed $returnValue): SoapClient
    {
        return new class($returnValue) extends SoapClient {
            public function __construct(private readonly mixed $response) { }

            public function __call(string $name, array $args): mixed
            {
                return $this->response;
            }
        };
    }

    private function makeFakeSoapFault(SoapFault $fault): SoapClient
    {
        return new class($fault) extends SoapClient {
            public function __construct(private readonly SoapFault $fault) { }

            public function __call(string $name, array $args): mixed
            {
                throw $this->fault;
            }
        };
    }

    public function test_get_response_returns_empty_array_first(): void
    {
        $service = new SoapRequestService();

        $this->assertSame([], $service->getResponse());
    }

    public function test_fetch_soap_throws_invalid_argument_for_unknown_departure_station(): void
    {
        $client = $this->makeFakeSoap($this->buildSoapResponse());
        $service = new SoapRequestService($client);
        $dto = $this->makeValidDto(departure: 'неизвестная');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/не найдена в списке станций/');

        $service->fetchSoap($dto);
    }

    public function test_fetch_soap_throws_runtime_exception_on_soap_fault(): void
    {
        $client = $this->makeFakeSoapFault(new SoapFault('Server', 'Connection refused'));
        $service = new SoapRequestService($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Ошибка SOAP при отправке запроса/');

        $service->fetchSoap($this->makeValidDto());
    }

    public function test_fetch_soap_throws_runtime_exception_when_route_list_missing(): void
    {
        $response = new stdClass();
        $response->train_description = new stdClass();

        $client = $this->makeFakeSoap($response);
        $service = new SoapRequestService($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/route_list/');

        $service->fetchSoap($this->makeValidDto());
    }

    public function test_fetch_soap_stores_response_on_success(): void
    {
        $client = $this->makeFakeSoap($this->buildSoapResponse());
        $service = new SoapRequestService($client);
        $service->fetchSoap($this->makeValidDto());

        $result = $service->getResponse();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('train_description', $result);
        $this->assertArrayHasKey('route_list', $result);
    }
}
