<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_version' => ['nullable', 'string', 'max:32'],
            'collected_at' => ['required', 'date'],

            'system' => ['required', 'array'],
            'system.hostname' => ['nullable', 'string', 'max:255'],
            'system.kernel' => ['nullable', 'string', 'max:255'],
            'system.uptime_seconds' => ['nullable', 'integer', 'min:0'],

            'system.cpu' => ['nullable', 'array'],
            'system.cpu.cores' => ['nullable', 'integer', 'min:0'],
            'system.cpu.usage_percent' => ['nullable', 'numeric', 'between:0,100'],
            'system.cpu.load_1' => ['nullable', 'numeric', 'min:0'],
            'system.cpu.load_5' => ['nullable', 'numeric', 'min:0'],
            'system.cpu.load_15' => ['nullable', 'numeric', 'min:0'],

            'system.memory' => ['nullable', 'array'],
            'system.memory.total_bytes' => ['nullable', 'integer', 'min:0'],
            'system.memory.available_bytes' => ['nullable', 'integer', 'min:0'],
            'system.memory.used_bytes' => ['nullable', 'integer', 'min:0'],
            'system.memory.used_percent' => ['nullable', 'numeric', 'between:0,100'],
            'system.memory.swap_total_bytes' => ['nullable', 'integer', 'min:0'],
            'system.memory.swap_used_bytes' => ['nullable', 'integer', 'min:0'],

            'system.disks' => ['nullable', 'array'],
            'system.disks.*.mount' => ['required_with:system.disks', 'string', 'max:255'],
            'system.disks.*.filesystem' => ['nullable', 'string', 'max:64'],
            'system.disks.*.total_bytes' => ['required_with:system.disks', 'integer', 'min:0'],
            'system.disks.*.used_bytes' => ['required_with:system.disks', 'integer', 'min:0'],
            'system.disks.*.available_bytes' => ['nullable', 'integer', 'min:0'],
            'system.disks.*.used_percent' => ['nullable', 'numeric', 'between:0,100'],

            'system.network' => ['nullable', 'array'],
            'system.network.*.iface' => ['required_with:system.network', 'string', 'max:64'],
            'system.network.*.rx_bytes' => ['nullable', 'integer', 'min:0'],
            'system.network.*.tx_bytes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
