<?php

namespace App\Http\Controllers;

use App\Cms\Mcp\PostTools;
use App\Models\McpToken;
use BadMethodCallException;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class McpServerController extends Controller
{
    private const VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    public function __invoke(Request $request, PostTools $tools): Response
    {
        $origin = $request->headers->get('Origin');
        $site = Request::create(config('app.url'))->getSchemeAndHttpHost();
        if ($origin !== null && $origin !== $site) {
            return $this->error(null, -32000, 'Origin is not allowed.', 403);
        }

        $secret = $request->bearerToken();
        $token = $secret ? McpToken::with('user')->where('token_hash', hash('sha256', $secret))->first() : null;
        if (! $token || ! $token->user?->isActive() || ! $token->user->isAdmin()) {
            return $this->error(null, -32000, 'A valid MCP bearer token is required.', 401)
                ->header('WWW-Authenticate', 'Bearer realm="MCP"');
        }
        if (! $request->isMethod('POST')) {
            return response('', 405, ['Allow' => 'POST']);
        }
        if (! $request->isJson()) {
            return $this->error(null, -32600, 'Send an application/json request.', 415);
        }
        $version = $request->header('MCP-Protocol-Version');
        if ($version !== null && ! in_array($version, self::VERSIONS, true)) {
            return $this->error(null, -32600, 'Unsupported MCP protocol version.', 400);
        }
        try {
            $message = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error(null, -32700, 'Invalid JSON.', 400);
        }
        if (! $message instanceof stdClass || ($message->jsonrpc ?? null) !== '2.0'
            || ! is_string($message->method ?? null)
            || (property_exists($message, 'id') && ! is_string($message->id) && ! is_int($message->id))) {
            return $this->error(null, -32600, 'Invalid JSON-RPC request.', 400);
        }
        if (! property_exists($message, 'id')) {
            // Notifications never dispatch a tool or produce a JSON-RPC reply.
            return response('', 202);
        }
        $id = $message->id;
        $params = property_exists($message, 'params') ? $message->params : new stdClass;
        if (! $params instanceof stdClass) {
            return $this->error($id, -32602, 'Expected a params object.');
        }

        switch ($message->method) {
            case 'initialize':
                if (! is_string($params->protocolVersion ?? null)) {
                    return $this->error($id, -32602, 'Supply a protocolVersion.');
                }
                $result = [
                    'protocolVersion' => in_array($params->protocolVersion, self::VERSIONS, true) ? $params->protocolVersion : self::VERSIONS[0],
                    'capabilities' => ['tools' => new stdClass],
                    'serverInfo' => ['name' => config('cms.name').' Blog', 'version' => config('cms.version')],
                ];
                break;
            case 'ping':
                $result = new stdClass;
                break;
            case 'tools/list':
                $result = ['tools' => $tools->definitions($token->can_write)];
                break;
            case 'tools/call':
                $arguments = property_exists($params, 'arguments') ? $params->arguments : new stdClass;
                if (! is_string($params->name ?? null) || ! $arguments instanceof stdClass) {
                    return $this->error($id, -32602, 'Supply a tool name and an arguments object.');
                }
                try {
                    auth()->setUser($token->user);
                    $data = $tools->call($params->name, (array) $arguments, $token);
                    $text = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $result = ['content' => [['type' => 'text', 'text' => $text]]];
                } catch (BadMethodCallException $e) {
                    return $this->error($id, -32602, 'Unknown or unavailable tool.');
                } catch (DomainException $e) {
                    $result = ['isError' => true, 'content' => [['type' => 'text', 'text' => $e->getMessage()]]];
                } catch (Throwable $e) {
                    report($e);

                    return $this->error($id, -32603, 'The server could not complete the tool call.');
                }
                break;
            default:
                return $this->error($id, -32601, 'Method not found.');
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result])
            ->header('Cache-Control', 'no-store');
    }

    private function error(int|string|null $id, int $code, string $message, int $status = 200): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => compact('code', 'message')], $status);
    }
}
