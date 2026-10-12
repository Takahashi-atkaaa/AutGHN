<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AIOrderParserService
{
    public function phanTich(string $noiDung): array
    {
        $apiKey = config('services.ai.api_key');
        $model = config('services.ai.model');

        if (! $apiKey || ! $model) {
            throw new RuntimeException('Chua cau hinh AI_API_KEY hoac AI_MODEL.');
        }

        $json = str_starts_with($model, 'gemini-')
            ? $this->goiGemini($apiKey, $model, $noiDung)
            : $this->goiOpenAi($apiKey, $model, $noiDung);

        $duLieu = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($duLieu)) {
            throw new RuntimeException('AI tra ve JSON khong hop le.');
        }

        $validator = Validator::make($duLieu, [
            'customer_name' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'cod' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('AI tra ve du lieu khong dung dinh dang.');
        }

        return [
            'customer_name' => $duLieu['customer_name'],
            'phone' => $duLieu['phone'],
            'address' => $duLieu['address'],
            'items' => array_map(fn (array $item) => [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
            ], $duLieu['items']),
            'cod' => $duLieu['cod'],
            'note' => $duLieu['note'],
        ];
    }

    private function goiOpenAi(string $apiKey, string $model, string $noiDung): string
    {
        $response = Http::retry(2, 500, null, false)
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'developer',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $this->huongDan(),
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $noiDung,
                        ]],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'don_hang_da_phan_tich',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("OpenAI API tra ve HTTP {$response->status()}.");
        }

        return $this->layNoiDungOpenAi($response->json());
    }

    private function goiGemini(string $apiKey, string $model, string $noiDung): string
    {
        $response = Http::retry(2, 500, null, false)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->acceptJson()
            ->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $this->huongDan()]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $noiDung]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $this->schemaGemini(),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Gemini API tra ve HTTP {$response->status()}.");
        }

        $json = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($json)) {
            throw new RuntimeException('Gemini khong tra ve noi dung phan tich.');
        }

        return $json;
    }

    private function layNoiDungOpenAi(array $phanHoi): string
    {
        if (is_string($phanHoi['output_text'] ?? null)) {
            return $phanHoi['output_text'];
        }

        foreach ($phanHoi['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('AI khong tra ve noi dung phan tich.');
    }

    private function huongDan(): string
    {
        return <<<'TEXT'
Ban la he thong trich xuat don hang giao hang tai Viet Nam. Phan tich tin nhan tu do, khong yeu cau nguoi dung viet theo mau.

Chi tra ve du lieu theo JSON schema. Khong duoc tu doan thong tin: customer_name, phone, address, cod, note phai la null neu khach khong cung cap ro rang.
Phone: chi giu chuoi chu so cua so dien thoai khach cung cap; bo khoang trang, dau cham, dau gach. Khong tu tao so.
COD: quy doi thanh VND dang so. Hieu 350k, 350 nghin, 350.000 la 350000; 1tr2 la 1200000; 1 trieu 200 la 1200000. Chi dien COD khi co ngu canh COD, thu ho, hoac so tien giao hang ro rang.
Items: trich xuat tung mat hang va so luong khach viet. Neu khong co so luong, dung 1. Khong kiem tra ton kho va khong tao SKU. Neu khong co hang, tra ve mang rong.
Note: chi lay yeu cau giao hang nhu giao chieu, giao gio hanh chinh, goi truoc khi giao, ship nhanh, de hang truoc cua.
TEXT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['customer_name', 'phone', 'address', 'items', 'cod', 'note'],
            'properties' => [
                'customer_name' => ['type' => ['string', 'null']],
                'phone' => ['type' => ['string', 'null']],
                'address' => ['type' => ['string', 'null']],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'quantity'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
                'cod' => ['type' => ['number', 'null'], 'minimum' => 0],
                'note' => ['type' => ['string', 'null']],
            ],
        ];
    }

    private function schemaGemini(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'customer_name' => ['type' => 'STRING', 'nullable' => true],
                'phone' => ['type' => 'STRING', 'nullable' => true],
                'address' => ['type' => 'STRING', 'nullable' => true],
                'items' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'quantity' => ['type' => 'INTEGER', 'minimum' => 1],
                    ],
                    'required' => ['name', 'quantity'],
                ]],
                'cod' => ['type' => 'NUMBER', 'nullable' => true, 'minimum' => 0],
                'note' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['customer_name', 'phone', 'address', 'items', 'cod', 'note'],
        ];
    }
}
