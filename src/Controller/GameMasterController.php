<?php

namespace App\Controller;

use App\Service\NpcGeneratorService;
use App\Service\OpenAiService;
use App\Service\PromptBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Game Master Controller
 *
 * This controller handles all game master interactions for the D&D-style RPG game.
 * It manages game sessions, player actions, dice rolls, and NPC interactions
 * by communicating with the OpenAI API to generate dynamic narrative content.
 */
#[Route('/api/game')]
class GameMasterController extends AbstractController
{
    public function __construct(
        private OpenAiService $openAiService,
        private NpcGeneratorService $npcGenerator,
        private PromptBuilderService $promptBuilder
    ) {}

    /**
     * Initialize a new game session with character context and NPCs
     *
     * This endpoint starts a new game session by:
     * 1. Generating companion NPCs based on party size
     * 2. Creating the initial game narrative
     * 3. Introducing the companions to the player
     *
     * @route POST /api/game/start
     * @param Request $request JSON body with character data, player count, and setting
     * @return JsonResponse Session data with introduction, NPCs, and session ID
     *
     * Request body example:
     * {
     *   "character": {
     *     "name": "Grimjaw",
     *     "race": "Orc",
     *     "class": "Barbare",
     *     "level": 1,
     *     "stats": {
     *       "strength": 18,
     *       "constitution": 16,
     *       "intelligence": 8,
     *       "wisdom": 10,
     *       "dexterity": 12,
     *       "charisma": 8
     *     }
     *   },
     *   "players": 4,
     *   "setting": "Terres Désolées d'Azeroth"
     * }
     */
    #[Route('/start', name: 'game_start', methods: ['POST'])]
    public function startGame(Request $request): JsonResponse
    {
        // Parse request data
        $data = json_decode($request->getContent(), true);
        $character = $data['character'] ?? null;
        $players = $data['players'] ?? 4;
        $setting = $data['setting'] ?? "Terres Désolées d'Azeroth";

        // Validate required data
        if (!$character) {
            return $this->json(['error' => 'Character data is required'], 400);
        }

        try {
            // Generate NPC companions (subtract 1 because player character counts)
            $npcs = $this->npcGenerator->generateNPCs($character, $players - 1);

            // Build the system prompt with character and NPC context
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($character, $players, $setting, $npcs);

            // Build the initial user prompt
            $userPrompt = $this->promptBuilder->buildGameStartPrompt($npcs);

            // Create message array for OpenAI
            $messages = [
                $this->openAiService->createMessage('system', $systemPrompt),
                $this->openAiService->createMessage('user', $userPrompt)
            ];

            // Get AI-generated introduction (600 tokens for longer introduction)
            $response = $this->openAiService->chat($messages, 600);

            // Return success response with game session data
            return $this->json([
                'success' => true,
                'sessionId' => uniqid('game_'),
                'introduction' => $response,
                'npcs' => $npcs,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error communicating with ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a player action and get the game master's response
     *
     * This endpoint handles player actions during the game by:
     * 1. Building the conversation context from history
     * 2. Submitting the action to the AI game master
     * 3. Returning the narrative consequences
     *
     * @route POST /api/game/action
     * @param Request $request JSON body with character, action, context, and history
     * @return JsonResponse Game master's response to the action
     *
     * Request body example:
     * {
     *   "character": { ... },
     *   "action": "Je m'avance prudemment dans le donjon",
     *   "context": {
     *     "location": "Donjon de Rochenoire",
     *     "previousEvents": ["..."],
     *     "partyMembers": ["Personnage 1", "Personnage 2"]
     *   },
     *   "history": [
     *     {"role": "assistant", "content": "..."},
     *     {"role": "user", "content": "..."}
     *   ]
     * }
     */
    #[Route('/action', name: 'game_action', methods: ['POST'])]
    public function playerAction(Request $request): JsonResponse
    {
        // Parse request data
        $data = json_decode($request->getContent(), true);
        $character = $data['character'] ?? null;
        $action = $data['action'] ?? null;
        $context = $data['context'] ?? [];
        $history = $data['history'] ?? [];

        // Validate required fields
        if (!$character || !$action) {
            return $this->json(['error' => 'Character and action are required'], 400);
        }

        try {
            // Build system prompt with current game context
            $systemPrompt = $this->promptBuilder->buildSystemPrompt(
                $character,
                4,
                $context['location'] ?? 'Donjon'
            );

            // Build user prompt for the action with HP context
            $userPrompt = $this->promptBuilder->buildPlayerActionPrompt($character, $action, $context);

            // Build complete message history including system prompt, history, and new action
            $messages = $this->openAiService->buildMessageHistory(
                $this->openAiService->createMessage('system', $systemPrompt),
                $history,
                $this->openAiService->createMessage('user', $userPrompt)
            );

            // Get AI response
            $response = $this->openAiService->chat($messages);

            return $this->json([
                'success' => true,
                'response' => $response,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error communicating with ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a dice roll result and get the game master's interpretation
     *
     * This endpoint resolves dice rolls by:
     * 1. Receiving the dice roll outcome
     * 2. Sending it to the AI game master for interpretation
     * 3. Returning the narrative outcome based on the roll
     *
     * @route POST /api/game/dice-result
     * @param Request $request JSON body with character, dice roll data, context, and history
     * @return JsonResponse Game master's response to the dice roll
     *
     * Request body example:
     * {
     *   "character": { ... },
     *   "diceRoll": {
     *     "type": "d20",
     *     "result": 18,
     *     "modifier": 5,
     *     "total": 23,
     *     "skillCheck": "Perception"
     *   },
     *   "context": "Le personnage essaie de détecter des pièges",
     *   "history": [...]
     * }
     */
    #[Route('/dice-result', name: 'game_dice_result', methods: ['POST'])]
    public function diceResult(Request $request): JsonResponse
    {
        // Parse request data
        $data = json_decode($request->getContent(), true);
        $character = $data['character'] ?? null;
        $diceRoll = $data['diceRoll'] ?? null;
        $context = $data['context'] ?? '';
        $gameContext = $data['gameContext'] ?? [];
        $history = $data['history'] ?? [];

        // Validate required fields
        if (!$character || !$diceRoll) {
            return $this->json(['error' => 'Character and dice roll are required'], 400);
        }

        try {
            // Build system prompt
            $systemPrompt = $this->promptBuilder->buildSystemPrompt($character, 4, 'Donjon');

            // Build dice result prompt with HP context
            $userPrompt = $this->promptBuilder->buildDiceResultPrompt($character, $diceRoll, $context, $gameContext);

            // Build complete message history
            $messages = $this->openAiService->buildMessageHistory(
                $this->openAiService->createMessage('system', $systemPrompt),
                $history,
                $this->openAiService->createMessage('user', $userPrompt)
            );

            // Get AI interpretation of the dice roll
            $response = $this->openAiService->chat($messages);

            return $this->json([
                'success' => true,
                'response' => $response,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error communicating with ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate an NPC's response to a game situation
     *
     * This endpoint allows NPCs to react to situations by:
     * 1. Using the NPC's personality and character traits
     * 2. Generating contextually appropriate dialogue
     * 3. Keeping responses short and in-character
     *
     * @route POST /api/game/npc-action
     * @param Request $request JSON body with NPC data, situation, and history
     * @return JsonResponse The NPC's response to the situation
     *
     * Request body example:
     * {
     *   "npc": {
     *     "name": "Elara la Sage",
     *     "race": "Elfe",
     *     "class": "Magicien"
     *   },
     *   "situation": "Combat avec des gobelins",
     *   "history": [...]
     * }
     */
    #[Route('/npc-action', name: 'game_npc_action', methods: ['POST'])]
    public function npcAction(Request $request): JsonResponse
    {
        // Parse request data
        $data = json_decode($request->getContent(), true);
        $npc = $data['npc'] ?? null;
        $situation = $data['situation'] ?? '';
        $history = $data['history'] ?? [];

        // Validate required fields
        if (!$npc) {
            return $this->json(['error' => 'NPC data is required'], 400);
        }

        try {
            // Build NPC-specific system prompt
            $systemPrompt = $this->promptBuilder->buildNpcSystemPrompt($npc);

            // Build action prompt for the situation
            $userPrompt = $this->promptBuilder->buildNpcActionPrompt($situation);

            // Build message history
            $messages = $this->openAiService->buildMessageHistory(
                $this->openAiService->createMessage('system', $systemPrompt),
                $history,
                $this->openAiService->createMessage('user', $userPrompt)
            );

            // Get AI response (shorter token limit for NPCs)
            $response = $this->openAiService->chat($messages, 150);

            return $this->json([
                'success' => true,
                'npcResponse' => $response,
                'npcName' => $npc['name'],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error communicating with ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }
}
