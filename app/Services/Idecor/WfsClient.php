<?php

namespace App\Services\Idecor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WfsClient
{
    private string $baseUrl;
    private int $pageSize;
    private string $srs;

    public function __construct()
    {
        $this->baseUrl = config('idecor.wfs_base_url');
        $this->pageSize = config('idecor.page_size');
        $this->srs = config('idecor.srs');
    }

    /**
     * Execute a WFS GetFeature request and return decoded features.
     *
     * @param string $typeName  Layer name (e.g. 'idecor:parcelas')
     * @param array  $options   Optional: cqlFilter, propertyName, maxFeatures, startIndex, srsName
     * @return array{features: array, totalFeatures: int, numberReturned: int}
     */
    public function getFeatures(string $typeName, array $options = []): array
    {
        $params = [
            'service' => 'WFS',
            'version' => '1.0.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => $options['srsName'] ?? $this->srs,
        ];

        if (isset($options['cqlFilter'])) {
            $params['CQL_FILTER'] = $options['cqlFilter'];
        }

        if (isset($options['propertyName'])) {
            $params['propertyName'] = $options['propertyName'];
        }

        $maxFeatures = $options['maxFeatures'] ?? $this->pageSize;
        $params['maxFeatures'] = $maxFeatures;

        if (isset($options['startIndex'])) {
            $params['startIndex'] = $options['startIndex'];
        }

        $url = $this->buildUrl($typeName, $params);

        Log::debug('WFS request', ['url' => $url]);

        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("WFS request failed: HTTP {$response->status()} - {$response->body()}");
        }

        $data = $response->json();

        if (! isset($data['features'])) {
            throw new \RuntimeException('WFS response missing features array: ' . substr($response->body(), 0, 500));
        }

        return [
            'features' => $data['features'],
            'totalFeatures' => $data['totalFeatures'] ?? count($data['features']),
            'numberReturned' => $data['numberReturned'] ?? count($data['features']),
        ];
    }

    /**
     * Paginate through ALL features of a layer. Yields arrays of features.
     *
     * @param string $typeName
     * @param array  $options  Same as getFeatures, minus maxFeatures/startIndex
     * @return \Generator<array> Each yield is an array of feature objects
     */
    public function paginateFeatures(string $typeName, array $options = []): \Generator
    {
        $startIndex = 0;
        $totalFeatures = null;

        do {
            $result = $this->getFeatures($typeName, array_merge($options, [
                'maxFeatures' => $this->pageSize,
                'startIndex' => $startIndex,
            ]));

            if ($totalFeatures === null) {
                $totalFeatures = $result['totalFeatures'];
            }

            if (empty($result['features'])) {
                break;
            }

            yield $result['features'];

            $startIndex += count($result['features']);
        } while ($startIndex < $totalFeatures);
    }

    /**
     * Count features matching a filter.
     */
    public function countFeatures(string $typeName, ?string $cqlFilter = null): int
    {
        $params = [
            'service' => 'WFS',
            'version' => '1.0.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'resultType' => 'hits',
        ];

        if ($cqlFilter) {
            $params['CQL_FILTER'] = $cqlFilter;
        }

        $url = $this->buildUrl($typeName, $params);
        $response = Http::timeout(60)->get($url);

        // hits returns XML with numberOfFeatures attribute
        if (preg_match('/numberOfFeatures="(\d+)"/', $response->body(), $matches)) {
            return (int) $matches[1];
        }

        // Fallback: fetch 1 feature and read totalFeatures
        $result = $this->getFeatures($typeName, [
            'cqlFilter' => $cqlFilter,
            'maxFeatures' => 1,
        ]);

        return $result['totalFeatures'];
    }

    /**
     * Build the WFS URL based on layer type.
     */
    private function buildUrl(string $typeName, array $params): string
    {
        // Parcelas layer uses its own workspace path
        if (str_contains($typeName, 'parcelas')) {
            $base = $this->baseUrl . '/parcelas/wfs';
        } else {
            $base = $this->baseUrl . '/wfs';
        }

        return $base . '?' . http_build_query($params);
    }
}
