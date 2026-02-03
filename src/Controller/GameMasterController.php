<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/game')]
class GameMasterController extends AbstractController
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $openaiApiKey
    ) {}

    /**
     * Initialise une nouvelle session de jeu avec le contexte du personnage
     *
     * POST /api/game/start
     * Body: {
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
        $data = json_decode($request->getContent(), true);

        $character = $data['character'] ?? null;
        $players = $data['players'] ?? 4;
        $setting = $data['setting'] ?? "Terres Désolées d'Azeroth";

        if (!$character) {
            return $this->json(['error' => 'Character data is required'], 400);
        }

        try {
            // Générer les PNJs compagnons
            $npcs = $this->generateNPCs($character, $players - 1); // -1 car le personnage principal compte

            // Créer le prompt initial pour ChatGPT avec les PNJs
            $systemPrompt = $this->buildSystemPrompt($character, $players, $setting, $npcs);

            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        'Commence l\'aventure. Présente brièvement les %d compagnons (%s) et décris la scène d\'ouverture.',
                        count($npcs),
                        implode(', ', array_column($npcs, 'name'))
                    )
                ]
            ], 600); // Plus de tokens pour l'introduction

            return $this->json([
                'success' => true,
                'sessionId' => uniqid('game_'),
                'introduction' => $response,
                'npcs' => $npcs,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la communication avec ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoie une action du joueur et récupère la réponse du maître du jeu
     *
     * POST /api/game/action
     * Body: {
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
        $data = json_decode($request->getContent(), true);

        $character = $data['character'] ?? null;
        $action = $data['action'] ?? null;
        $context = $data['context'] ?? [];
        $history = $data['history'] ?? [];

        if (!$character || !$action) {
            return $this->json(['error' => 'Character and action are required'], 400);
        }

        try {
            // Construire les messages pour ChatGPT avec l'historique
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($character, 4, $context['location'] ?? 'Donjon')
                ]
            ];

            // Ajouter l'historique des messages
            foreach ($history as $msg) {
                $messages[] = $msg;
            }

            // Ajouter l'action actuelle
            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    "%s fait l'action suivante : %s\n\nRéponds en tant que Maître du Jeu et décris les conséquences. Si nécessaire, demande un jet de dé.",
                    $character['name'],
                    $action
                )
            ];

            $response = $this->callOpenAI($messages);

            return $this->json([
                'success' => true,
                'response' => $response,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la communication avec ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Résout un jet de dé et obtient la réponse du maître du jeu
     *
     * POST /api/game/dice-result
     * Body: {
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
        $data = json_decode($request->getContent(), true);

        $character = $data['character'] ?? null;
        $diceRoll = $data['diceRoll'] ?? null;
        $context = $data['context'] ?? '';
        $history = $data['history'] ?? [];

        if (!$character || !$diceRoll) {
            return $this->json(['error' => 'Character and dice roll are required'], 400);
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($character, 4, 'Donjon')
                ]
            ];

            foreach ($history as $msg) {
                $messages[] = $msg;
            }

            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    "%s a lancé %s pour %s.\nRésultat du dé: %d + %d = %d\nContexte: %s\n\nEn tant que Maître du Jeu, décris le résultat de cette action selon le jet de dé.",
                    $character['name'],
                    $diceRoll['type'] ?? 'd20',
                    $diceRoll['skillCheck'] ?? 'une action',
                    $diceRoll['result'] ?? 0,
                    $diceRoll['modifier'] ?? 0,
                    $diceRoll['total'] ?? 0,
                    $context
                )
            ];

            $response = $this->callOpenAI($messages);

            return $this->json([
                'success' => true,
                'response' => $response,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la communication avec ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère une réponse d'un autre joueur NPC
     *
     * POST /api/game/npc-action
     * Body: {
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
        $data = json_decode($request->getContent(), true);

        $npc = $data['npc'] ?? null;
        $situation = $data['situation'] ?? '';
        $history = $data['history'] ?? [];

        if (!$npc) {
            return $this->json(['error' => 'NPC data is required'], 400);
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => sprintf(
                        "Tu es %s, un personnage %s de classe %s dans un jeu de rôle. Tu dois réagir de manière cohérente avec ton personnage. Réponds en une ou deux phrases courtes comme si tu parlais en tant que ce personnage.",
                        $npc['name'],
                        $npc['race'] ?? 'inconnu',
                        $npc['class'] ?? 'aventurier'
                    )
                ]
            ];

            foreach ($history as $msg) {
                $messages[] = $msg;
            }

            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    "Situation actuelle: %s\n\nComment réagis-tu ou que fais-tu ?",
                    $situation
                )
            ];

            $response = $this->callOpenAI($messages, 150); // Limite de tokens plus courte pour les NPCs

            return $this->json([
                'success' => true,
                'npcResponse' => $response,
                'npcName' => $npc['name'],
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la communication avec ChatGPT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère des PNJs compagnons pour la partie
     */
    private function generateNPCs(array $character, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $races = ['Elfe', 'Nain', 'Humain', 'Halfelin', 'Demi-Elfe', 'Tiefling'];
        $classes = ['Guerrier', 'Magicien', 'Roublard', 'Clerc', 'Rôdeur', 'Paladin', 'Barde', 'Druide'];

        $namesByRace = [
            'Elfe' => ['Elara', 'Thranduil', 'Galadriel', 'Legolas', 'Arwen'],
            'Nain' => ['Thorin', 'Gimli', 'Balin', 'Dwalin', 'Dori'],
            'Humain' => ['Aragorn', 'Boromir', 'Éowyn', 'Faramir', 'Théoden'],
            'Halfelin' => ['Bilbo', 'Frodo', 'Sam', 'Merry', 'Pippin'],
            'Demi-Elfe' => ['Elrond', 'Elladan', 'Elrohir', 'Estel'],
            'Tiefling' => ['Zariel', 'Moloch', 'Levistus', 'Glasya']
        ];

        $npcs = [];
        $usedCombinations = [];

        for ($i = 0; $i < $count; $i++) {
            // Éviter les doublons de race/classe
            do {
                $race = $races[array_rand($races)];
                $class = $classes[array_rand($classes)];
                $combination = "$race-$class";
            } while (in_array($combination, $usedCombinations));

            $usedCombinations[] = $combination;

            // Choisir un nom approprié
            $names = $namesByRace[$race] ?? ['Compagnon'];
            $baseName = $names[array_rand($names)];
            $name = $baseName;

            // Ajouter un suffixe si le nom existe déjà
            $counter = 1;
            while (in_array($name, array_column($npcs, 'name'))) {
                $name = $baseName . ' ' . ['le Brave', 'le Sage', 'l\'Ancien', 'le Jeune', 'le Rapide'][$counter % 5];
                $counter++;
            }

            $npcs[] = [
                'name' => $name,
                'race' => $race,
                'class' => $class,
                'personality' => $this->generatePersonality($class),
                'level' => $character['level'] ?? 1
            ];
        }

        return $npcs;
    }

    /**
     * Génère une personnalité pour un PNJ selon sa classe
     */
    private function generatePersonality(string $class): string
    {
        $personalities = [
            'Guerrier' => ['brave', 'loyal', 'protecteur', 'direct'],
            'Magicien' => ['intellectuel', 'curieux', 'prudent', 'mystérieux'],
            'Roublard' => ['rusé', 'agile', 'cynique', 'opportuniste'],
            'Clerc' => ['pieux', 'compatissant', 'sage', 'dévoué'],
            'Rôdeur' => ['indépendant', 'silencieux', 'observateur', 'proche de la nature'],
            'Paladin' => ['honorable', 'juste', 'déterminé', 'charismatique'],
            'Barde' => ['charmant', 'créatif', 'sociable', 'optimiste'],
            'Druide' => ['sage', 'pacifique', 'mystique', 'en harmonie avec la nature']
        ];

        $traits = $personalities[$class] ?? ['équilibré'];
        return $traits[array_rand($traits)];
    }

    /**
     * Construit le prompt système pour ChatGPT
     */
    private function buildSystemPrompt(array $character, int $players, string $setting, array $npcs = []): string
    {
        $stats = $character['stats'] ?? [];

        $npcsList = '';
        if (!empty($npcs)) {
            $npcsList = "\n\nLes compagnons PNJs du groupe :\n";
            foreach ($npcs as $npc) {
                $npcsList .= sprintf(
                    "- %s : %s %s (niveau %d, personnalité : %s)\n",
                    $npc['name'],
                    $npc['race'],
                    $npc['class'],
                    $npc['level'],
                    $npc['personality']
                );
            }
            $npcsList .= "\nTu dois incarner ces PNJs et les faire réagir de manière cohérente avec leur personnalité.";
        }

        return sprintf(
            "Tu es un Maître du Jeu expert dans Donjons & Dragons 5e. Tu guides une aventure épique dans le monde de %s.

Le personnage principal joué par l'utilisateur :
- Nom: %s
- Race: %s
- Classe: %s
- Niveau: %d
- Caractéristiques:
  * Force: %d
  * Constitution: %d
  * Intelligence: %d
  * Sagesse: %d
  * Dextérité: %d
  * Charisme: %d
%s
Il y a %d joueurs dans la partie (incluant le personnage principal).

RÈGLES IMPORTANTES:
1. Structure tes réponses de manière claire avec des paragraphes courts (2-3 phrases max)
2. Utilise des sauts de ligne pour séparer les différentes informations
3. Mets en évidence les éléments importants (jets de dés, dangers, choix)
4. Utilise les règles de D&D 5e pour les jets de dés et difficultés
5. Demande des jets de dés quand approprié en les mettant sur une ligne séparée
6. Fais réagir l'environnement et les PNJs de manière dynamique
7. Crée des situations intéressantes et des choix moraux
8. Adapte la difficulté au niveau du personnage
9. Reste cohérent avec l'univers fantasy et les capacités du personnage
10. Réponds en français, dans un style narratif épique mais concis
11. Fais intervenir les PNJs compagnons de manière naturelle et selon leur personnalité

FORMAT DE RÉPONSE :
- Commence par une description courte de la scène (1-2 phrases)
- Si des PNJs réagissent, mets leurs dialogues entre guillemets sur des lignes séparées
- Si un jet de dé est nécessaire, termine par : \"⚔️ Jet requis : [Compétence] (DD [Difficulté])\"
- Utilise des émojis occasionnellement pour plus de clarté (⚔️ combat, 🔍 investigation, 💬 dialogue, ⚠️ danger)

Exemple de bonne réponse :
\"Vous poussez les lourdes portes qui grincent dans l'obscurité. L'air est humide et une odeur de moisissure vous assaille.

Elara murmure une incantation et une lueur bleutée éclaire le couloir. \"Je détecte de la magie résiduelle...\"

Au sol, vous remarquez des traces fraîches menant vers les profondeurs.

⚔️ Jet requis : Perception (DD 13) pour détecter d'éventuels pièges\"

Commence chaque réponse en restant en immersion totale dans le rôle du Maître du Jeu.",
            $setting,
            $character['name'] ?? 'Aventurier',
            $character['race'] ?? 'Humain',
            $character['class'] ?? 'Guerrier',
            $character['level'] ?? 1,
            $stats['strength'] ?? 10,
            $stats['constitution'] ?? 10,
            $stats['intelligence'] ?? 10,
            $stats['wisdom'] ?? 10,
            $stats['dexterity'] ?? 10,
            $stats['charisma'] ?? 10,
            $npcsList,
            $players
        );
    }

    /**
     * Effectue un appel à l'API OpenAI
     */
    private function callOpenAI(array $messages, int $maxTokens = 500): string
    {
        $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.8, // Créativité modérée
                'top_p' => 1,
                'frequency_penalty' => 0.3, // Évite les répétitions
                'presence_penalty' => 0.3, // Encourage la diversité
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Réponse invalide de l\'API OpenAI');
        }

        return trim($data['choices'][0]['message']['content']);
    }
}
