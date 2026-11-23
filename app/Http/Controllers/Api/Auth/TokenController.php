<?php

namespace App\\Http\\Controllers\\Api\\Auth;

use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Laravel\\Sanctum\\PersonalAccessToken;

class TokenController
{
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'लॉगआउट सफल हुआ।']);
    }
}
