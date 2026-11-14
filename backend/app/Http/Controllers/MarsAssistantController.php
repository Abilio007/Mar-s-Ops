<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AIUserAssistent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;

class MarsAssistantController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'uuid'],
        ]);
        if (! isset($validated['conversation_id'])) {
            return response()->json(['data' => []]);
        }

        $conversation = $this->ownedConversation($validated['conversation_id'], $request->user());
        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->oldest()
            ->get()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'text' => $message->content,
                'timestamp' => $message->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $messages]);
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:800'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        $conversationId = $validated['conversation_id'] ?? null;
        if ($conversationId !== null) {
            $this->ownedConversation($conversationId, $user);
        }

        $agent = (new AIUserAssistent)->continueOrStart($conversationId, $user);
        $response = $agent->prompt(
            trim($validated['message']),
            provider: (string) config('services.mars.ai_provider', 'gemini'),
            model: (string) config('services.mars.ai_model', 'gemini-2.5-flash'),
            timeout: (int) config('services.mars.ai_timeout', 45),
        );

        return response()->json([
            'data' => [
                'message' => $response->text,
                'conversation_id' => $response->conversationId,
            ],
        ]);
    }

    public function destroyConversation(Request $request, string $conversationId): JsonResponse
    {
        $conversation = $this->ownedConversation($conversationId, $request->user());
        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }

    private function ownedConversation(string $conversationId, object $user): Conversation
    {
        return Conversation::query()
            ->whereKey($conversationId)
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', Conversation::participantKey($user))
            ->firstOrFail();
    }
}
