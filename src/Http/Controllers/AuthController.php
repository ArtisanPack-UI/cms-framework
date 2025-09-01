<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use ArtisanPackUI\CMSFramework\Models\User;
use OpenApi\Attributes as OA;

/**
 * Authentication Controller.
 *
 * Handles authentication operations including login, logout, and token management.
 * Provides Laravel Sanctum token-based authentication for API access.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
#[OA\Schema(
    schema: "LoginRequest",
    type: "object",
    description: "User login request",
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", description: "User email address", example: "admin@example.com"),
        new OA\Property(property: "password", type: "string", description: "User password", example: "secretpassword123"),
        new OA\Property(property: "device_name", type: "string", nullable: true, description: "Device name for token identification", example: "Mobile App")
    ]
)]
#[OA\Schema(
    schema: "LoginResponse",
    type: "object",
    description: "Successful login response",
    properties: [
        new OA\Property(property: "token", type: "string", description: "Bearer token for API authentication", example: "1|abc123def456ghi789..."),
        new OA\Property(property: "user", ref: "#/components/schemas/User"),
        new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true, description: "Token expiration time", example: "2025-09-26T10:00:00Z")
    ]
)]
class AuthController
{
    /**
     * Authenticate user and return access token.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\JsonResponse Authentication response with token.
     */
    #[OA\Post(
        path: "/api/cms/auth/login",
        operationId: "loginUser",
        description: "Authenticate user credentials and return a Sanctum access token for API access.",
        summary: "User login",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/LoginRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(ref: "#/components/schemas/LoginResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (5 requests per minute for auth endpoints)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        $deviceName = $request->device_name ?? 'API Token';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'expires_at' => null // Sanctum tokens don't expire by default
        ]);
    }

    /**
     * Logout user and revoke current access token.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\JsonResponse Logout confirmation response.
     */
    #[OA\Post(
        path: "/api/cms/auth/logout",
        operationId: "logoutUser",
        description: "Revoke the current access token and logout the authenticated user.",
        summary: "User logout",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout successful",
                content: new OA\JsonContent(ref: "#/components/schemas/Success")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices by revoking all access tokens.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\JsonResponse Logout confirmation response.
     */
    #[OA\Post(
        path: "/api/cms/auth/logout-all",
        operationId: "logoutAllDevices",
        description: "Revoke all access tokens for the authenticated user, logging out from all devices.",
        summary: "Logout from all devices",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout from all devices successful",
                content: new OA\JsonContent(ref: "#/components/schemas/Success")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    /**
     * Get current authenticated user information.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\JsonResponse Current user data.
     */
    #[OA\Get(
        path: "/api/cms/auth/user",
        operationId: "getCurrentUser",
        description: "Retrieve the currently authenticated user's information.",
        summary: "Get current user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Current user information",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}