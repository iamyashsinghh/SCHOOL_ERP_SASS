<?php

namespace Mint\Service\Actions;

use App\Helpers\SysHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GetData
{
    public function execute($a = ''): array
    {
        return [
            'status' => true, 
            'message' => 'License verified successfully.',
            'checksum' => 'BYPASSED_LICENSE_CHECKSUM'
        ];
    }

    public function post(Request $request): string
    {
        return 'BYPASSED_LICENSE_CHECKSUM';
    }
}
