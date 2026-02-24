<?php

namespace App\DTO;

use App\Casts\IntCast;
use App\Casts\LowercaseCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class TrainPathRequestDTO extends Data
{
    public function __construct(
        public readonly string $train_number,

        #[WithCast(LowercaseCast::class)]
        public readonly string $departure_station,

        #[WithCast(LowercaseCast::class)]
        public readonly string $arrival_station,

        #[WithCast(IntCast::class)]
        public readonly int $day,

        #[WithCast(IntCast::class)]
        public readonly int $month,
    ) { }

    public static function rules(): array
    {
        return [
            'train_number' => ['required', 'string', 'max:20'],
            'departure_station' => ['required', 'string', 'max:100'],
            'arrival_station' => ['required', 'string', 'different:departure_station', 'max:100'],
            'day' => ['required', 'numeric', 'min:1', 'max:31'],
            'month' => ['required', 'numeric', 'min:1', 'max:12'],
        ];
    }

    public static function messages(): array
    {
        return [
            'train_number.required' => 'Ошибка ввода Номера поезда: поле обязательно для заполнения',
            'train_number.string' => 'Ошибка ввода Номера поезда: должен быть строкой',
            'train_number.max' => 'Ошибка ввода Номера поезда: должен содержать не более 20 символов',

            'departure_station.required' => 'Ошибка ввода Станции отправления: поле обязательно для заполнения',
            'departure_station.string' => 'Ошибка ввода Станции отправления: должна быть строкой',
            'departure_station.max' => 'Ошибка ввода Станции отправления: должна содержать не более 100 символов',

            'arrival_station.required' => 'Ошибка ввода Станции прибытия: поле обязательно для заполнения',
            'arrival_station.string' => 'Ошибка ввода Станции прибытия: должна быть строкой',
            'arrival_station.max' => 'Ошибка ввода Станции прибытия: должна содержать не более 100 символов',
            'arrival_station.different' => 'Ошибка ввода Станции прибытия: должна отличаться от Станции отправления',

            'day.required' => 'Ошибка ввода Дня: поле обязательно для заполнения',
            'day.integer' => 'Ошибка ввода Дня: должен быть целым числом',
            'day.min' => 'Ошибка ввода Дня: должен быть от 1 до 31',
            'day.max' => 'Ошибка ввода Дня: должен быть от 1 до 31',

            'month.required' => 'Ошибка ввода Месяца: поле обязательно для заполнения',
            'month.integer' => 'Ошибка ввода Месяца: должен быть целым числом',
            'month.min' => 'Ошибка ввода Месяца: должен быть от 1 до 12',
            'month.max' => 'Ошибка ввода Месяца: должен быть от 1 до 12',
        ];
    }

    public function getArray(): array
    {
        return [
            $this->train_number,
            $this->departure_station,
            $this->arrival_station,
            $this->day,
            $this->month,
        ];
    }
}
