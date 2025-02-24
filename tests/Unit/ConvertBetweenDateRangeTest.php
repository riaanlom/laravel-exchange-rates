<?php

namespace AshAllenDesign\LaravelExchangeRates\Tests\Unit;

use AshAllenDesign\LaravelExchangeRates\Classes\ExchangeRate;
use AshAllenDesign\LaravelExchangeRates\Classes\RequestBuilder;
use AshAllenDesign\LaravelExchangeRates\Exceptions\ExchangeRateException;
use AshAllenDesign\LaravelExchangeRates\Exceptions\InvalidCurrencyException;
use AshAllenDesign\LaravelExchangeRates\Exceptions\InvalidDateException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ConvertBetweenDateRangeTest extends TestCase
{
    /** @test */
    public function converted_values_between_date_range_are_returned_and_exchange_rates_are_not_cached()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')
            ->withArgs([
                '/timeseries',
                [
                    'base'     => 'GBP',
                    'start_at' => $fromDate->format('Y-m-d'),
                    'end_at'   => $toDate->format('Y-m-d'),
                    'symbols'  => 'EUR',
                ],
            ])
            ->once()
            ->andReturn($this->mockResponseForOneSymbol());

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->convertBetweenDateRange(100, 'GBP', 'EUR', $fromDate, $toDate);

        $this->assertEquals([
            '2019-11-08' => 116.06583254,
            '2019-11-06' => 116.23446817,
            '2019-11-07' => 115.68450522,
            '2019-11-05' => 116.12648497,
            '2019-11-04' => 115.78362356,
        ], $currencies);

        $cachedExchangeRates = [
            '2019-11-08' => 1.1606583254,
            '2019-11-06' => 1.1623446817,
            '2019-11-07' => 1.1568450522,
            '2019-11-05' => 1.1612648497,
            '2019-11-04' => 1.1578362356,
        ];
        $this->assertEquals($cachedExchangeRates,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function cached_exchange_rates_are_used_if_they_exist()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $cacheKey = 'laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d');
        $cachedValues = $expectedArray = [
            '2019-11-08' => 0.111,
            '2019-11-06' => 0.222,
            '2019-11-07' => 0.333,
            '2019-11-05' => 0.444,
            '2019-11-04' => 0.555,
        ];
        Cache::forever($cacheKey, $cachedValues);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')->never();

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->convertBetweenDateRange(100, 'GBP', 'EUR', $fromDate, $toDate);

        $this->assertEquals([
            '2019-11-08' => 11.1,
            '2019-11-06' => 22.2,
            '2019-11-07' => 33.3,
            '2019-11-05' => 44.4,
            '2019-11-04' => 55.5,
        ], $currencies);

        $this->assertEquals($expectedArray,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function cached_exchange_rate_is_ignored_if_should_bust_cache_method_is_used()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $cacheKey = 'laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d');
        $cachedValues = $expectedArray = [
            '2019-11-08' => 0.111,
            '2019-11-06' => 0.222,
            '2019-11-07' => 0.333,
            '2019-11-05' => 0.444,
            '2019-11-04' => 0.555,
        ];
        Cache::forever($cacheKey, $cachedValues);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')
            ->withArgs([
                '/timeseries',
                [
                    'base'     => 'GBP',
                    'start_at' => $fromDate->format('Y-m-d'),
                    'end_at'   => $toDate->format('Y-m-d'),
                    'symbols'  => 'EUR',
                ],
            ])
            ->once()
            ->andReturn($this->mockResponseForOneSymbol());

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->shouldBustCache()->convertBetweenDateRange(100, 'GBP', 'EUR', $fromDate, $toDate);

        $this->assertEquals([
            '2019-11-08' => 116.06583254,
            '2019-11-06' => 116.23446817,
            '2019-11-07' => 115.68450522,
            '2019-11-05' => 116.12648497,
            '2019-11-04' => 115.78362356,
        ], $currencies);

        $cachedExchangeRates = [
            '2019-11-08' => 1.1606583254,
            '2019-11-06' => 1.1623446817,
            '2019-11-07' => 1.1568450522,
            '2019-11-05' => 1.1612648497,
            '2019-11-04' => 1.1578362356,
        ];
        $this->assertEquals($cachedExchangeRates,
            Cache::get('laravel_xr_GBP_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function converted_values_can_be_returned_for_multiple_currencies()
    {
        $fromDate = now()->subWeek();
        $toDate = now();

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')
            ->withArgs([
                '/timeseries',
                [
                    'base'     => 'GBP',
                    'start_at' => $fromDate->format('Y-m-d'),
                    'end_at'   => $toDate->format('Y-m-d'),
                    'symbols'  => 'EUR,USD',
                ],
            ])
            ->once()
            ->andReturn($this->mockResponseForMultipleSymbols());

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->convertBetweenDateRange(100, 'GBP', ['EUR', 'USD'], $fromDate, $toDate);

        $expectedArray = [
            '2019-11-08' => ['EUR' => 116.06583254, 'USD' => 111.11111111],
            '2019-11-06' => ['EUR' => 116.23446817, 'USD' => 122.22222222],
            '2019-11-07' => ['EUR' => 115.68450522, 'USD' => 133.33333333],
            '2019-11-05' => ['EUR' => 116.12648497, 'USD' => 144.44444444],
            '2019-11-04' => ['EUR' => 115.78362356, 'USD' => 155.55555555],
        ];

        $this->assertEquals($expectedArray, $currencies);

        $cachedExchangeRates = [
            '2019-11-08' => ['EUR' => 1.1606583254, 'USD' => 1.1111111111],
            '2019-11-06' => ['EUR' => 1.1623446817, 'USD' => 1.2222222222],
            '2019-11-07' => ['EUR' => 1.1568450522, 'USD' => 1.3333333333],
            '2019-11-05' => ['EUR' => 1.1612648497, 'USD' => 1.4444444444],
            '2019-11-04' => ['EUR' => 1.1578362356, 'USD' => 1.5555555555],
        ];
        $this->assertEquals($cachedExchangeRates,
            Cache::get('laravel_xr_GBP_EUR_USD_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function request_is_not_made_if_the_currencies_are_the_same()
    {
        $fromDate = Carbon::createFromDate(2019, 11, 4);
        $toDate = Carbon::createFromDate(2019, 11, 10);

        $requestBuilderMock = Mockery::mock(RequestBuilder::class)->makePartial();
        $requestBuilderMock->expects('makeRequest')->withAnyArgs()->never();

        $exchangeRate = new ExchangeRate($requestBuilderMock);
        $currencies = $exchangeRate->convertBetweenDateRange(100, 'EUR', 'EUR', $fromDate, $toDate);

        $this->assertEquals([
            '2019-11-08' => 100.0,
            '2019-11-06' => 100.0,
            '2019-11-07' => 100.0,
            '2019-11-05' => 100.0,
            '2019-11-04' => 100.0,
        ], $currencies);

        $cachedExchangeRates = [
            '2019-11-08' => 1.0,
            '2019-11-06' => 1.0,
            '2019-11-07' => 1.0,
            '2019-11-05' => 1.0,
            '2019-11-04' => 1.0,
        ];
        $this->assertEquals($cachedExchangeRates,
            Cache::get('laravel_xr_EUR_EUR_'.$fromDate->format('Y-m-d').'_'.$toDate->format('Y-m-d')));
    }

    /** @test */
    public function exception_is_thrown_if_the_date_parameter_passed_is_in_the_future()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('The date must be in the past.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'EUR', 'GBP', now()->addMinute(), now()->subDay());
    }

    /** @test */
    public function exception_is_thrown_if_the_end_date_parameter_passed_is_in_the_future()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('The date must be in the past.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'EUR', 'GBP', now()->subDay(), now()->addMinute());
    }

    /** @test */
    public function exception_is_thrown_if_the_end_date_is_before_the_start_date()
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage("The 'from' date must be before the 'to' date.");

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'EUR', 'GBP', now()->subDay(), now()->subWeek());
    }

    /** @test */
    public function exception_is_thrown_if_the_from_parameter_is_invalid()
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('INVALID is not a valid currency code.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'INVALID', 'GBP', now()->subWeek(), now()->subDay());
    }

    /** @test */
    public function exception_is_thrown_if_the_to_parameter_is_invalid()
    {
        $this->expectException(InvalidCurrencyException::class);
        $this->expectExceptionMessage('INVALID is not a valid currency code.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'GBP', 'INVALID', now()->subWeek(), now()->subDay());
    }

    /** @test */
    public function exception_is_thrown_if_the_to_parameter_is_not_a_string_or_array()
    {
        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessage('123 is not a string or array.');

        $exchangeRate = new ExchangeRate();
        $exchangeRate->convertBetweenDateRange(100, 'GBP', 123, now()->subWeek(), now()->subDay());
    }

    private function mockResponseForOneSymbol()
    {
        return [
            'rates'    => [
                '2019-11-08' => [
                    'EUR' => 1.1606583254,
                ],
                '2019-11-06' => [
                    'EUR' => 1.1623446817,
                ],
                '2019-11-07' => [
                    'EUR' => 1.1568450522,
                ],
                '2019-11-05' => [
                    'EUR' => 1.1612648497,
                ],
                '2019-11-04' => [
                    'EUR' => 1.1578362356,
                ],
            ],
            'start_at' => '2019-11-03',
            'base'     => 'GBP',
            'end_at'   => '2019-11-10',
        ];
    }

    private function mockResponseForMultipleSymbols()
    {
        return [
            'rates'    => [
                '2019-11-08' => [
                    'EUR' => 1.1606583254,
                    'USD' => 1.1111111111,
                ],
                '2019-11-06' => [
                    'EUR' => 1.1623446817,
                    'USD' => 1.2222222222,
                ],
                '2019-11-07' => [
                    'EUR' => 1.1568450522,
                    'USD' => 1.3333333333,
                ],
                '2019-11-05' => [
                    'EUR' => 1.1612648497,
                    'USD' => 1.4444444444,
                ],
                '2019-11-04' => [
                    'EUR' => 1.1578362356,
                    'USD' => 1.5555555555,
                ],
            ],
            'start_at' => '2019-11-03',
            'base'     => 'GBP',
            'end_at'   => '2019-11-10',
        ];
    }
}
