<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class McpController extends Controller
{
    public function index(): Response
    {
        return response()->view('admin.mcp.index', [
            'tokens' => McpToken::with('user')->latest('id')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', 'in:read,write'],
        ]);

        $secret = 'mcp_'.Str::random(64);
        $token = McpToken::create([
            'name' => $input['name'],
            'user_id' => $request->user()->id,
            'token_hash' => hash('sha256', $secret),
            'can_write' => $input['access'] === 'write',
        ]);
        activity('mcp.token.created', "Issued an MCP token for {$token->name}.", $token);

        return to_route('admin.mcp.index')->with('mcp_secret', $secret);
    }

    public function destroy(McpToken $token): RedirectResponse
    {
        $token->delete();
        activity('mcp.token.revoked', "Revoked the MCP token for {$token->name}.");

        return to_route('admin.mcp.index')->with('status', 'Token revoked.');
    }
}
