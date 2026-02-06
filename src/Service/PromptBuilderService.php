<?php

namespace App\Service;

/**
 * Service for building AI prompts for the Game Master
 *
 * This service is responsible for creating properly formatted prompts
 * for the OpenAI API to act as a Dungeon Master in D&D 5e games.
 */
class PromptBuilderService
{
    /**
     * Build the system prompt for the AI Game Master
     *
     * Creates a detailed prompt that instructs the AI on how to act as a
     * Dungeon Master, including character context, game rules, and response formatting.
     *
     * @param array $character Player character data (name, race, class, stats, etc.)
     * @param int $playerCount Total number of players in the party
     * @param string $setting The game world/setting (e.g., "Forgotten Realms")
     * @param array $npcs List of NPC companions in the party
     * @return string The complete system prompt for the AI
     */
    public function buildSystemPrompt(
        array $character,
        int $playerCount,
        string $setting,
        array $npcs = []
    ): string {
        $stats = $character['stats'] ?? [];
        $npcsList = $this->buildNpcsDescription($npcs);

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
12. **OBLIGATOIRE** : Termine TOUJOURS ta réponse par une question ou un choix pour le joueur

FORMAT DE RÉPONSE OBLIGATOIRE :
- Commence par une description courte de la scène (1-2 phrases)
- Si des PNJs réagissent, mets leurs dialogues entre guillemets sur des lignes séparées
- Si un jet de dé est nécessaire, indique-le clairement : \"⚔️ Jet requis : [Compétence] (DD [Difficulté])\"
- **TERMINE TOUJOURS** par une question directe au joueur (Que faites-vous ? / Comment réagissez-vous ? / Quelle est votre décision ?)
- Utilise des émojis occasionnellement pour plus de clarté (⚔️ combat, 🔍 investigation, 💬 dialogue, ⚠️ danger, ❓ choix)

Exemples de bonnes réponses :

Exemple 1 (Exploration) :
\"Vous poussez les lourdes portes qui grincent dans l'obscurité. L'air est humide et une odeur de moisissure vous assaille.

Elara murmure une incantation et une lueur bleutée éclaire le couloir. \"Je détecte de la magie résiduelle...\"

Au sol, vous remarquez des traces fraîches menant vers les profondeurs.

❓ Que faites-vous ?\"

Exemple 2 (Combat imminent) :
\"Des grognements résonnent depuis les ombres. Trois silhouettes se rapprochent lentement.

Thorin serre le pommeau de son épée. \"Préparez-vous au combat...\"

⚔️ Jet requis : Initiative (1d20 + modificateur de Dextérité)

❓ Comment vous positionnez-vous pour le combat ?\"

Exemple 3 (Choix moral) :
\"Le garde blessé vous supplie de l'épargner. \"J'ai une famille... Je vous en prie...\"

Bilbo chuchote : \"On pourrait le laisser partir... Ou l'interroger d'abord.\"

💬 Que décidez-vous ?\"

Exemple 4 (Investigation) :
\"La salle est jonchée de grimoires poussiéreux. Au centre, un piédestal soutient une gemme rougeoyante.

Elara s'approche prudemment. \"Cette magie est puissante... Et dangereuse.\"

🔍 Jet requis : Arcanes (DD 15) pour identifier la gemme

❓ Voulez-vous tenter d'identifier la gemme ou l'ignorer ?\"

RAPPEL CRITIQUE : Ne termine JAMAIS une réponse sans poser une question au joueur. Même après un jet de dé réussi, demande toujours \"Que faites-vous ensuite ?\" ou une variante.

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
            $playerCount
        );
    }

    /**
     * Build a description of the NPC companions for inclusion in prompts
     *
     * @param array $npcs List of NPCs
     * @return string Formatted description of NPCs or empty string
     */
    private function buildNpcsDescription(array $npcs): string
    {
        if (empty($npcs)) {
            return '';
        }

        $description = "\n\nLes compagnons PNJs du groupe :\n";

        foreach ($npcs as $npc) {
            $description .= sprintf(
                "- %s : %s %s (niveau %d, personnalité : %s)\n",
                $npc['name'],
                $npc['race'],
                $npc['class'],
                $npc['level'],
                $npc['personality']
            );
        }

        $description .= "\nTu dois incarner ces PNJs et les faire réagir de manière cohérente avec leur personnalité.";

        return $description;
    }

    /**
     * Build user prompt for game start
     *
     * @param array $npcs List of NPC companions
     * @return string Formatted user prompt
     */
    public function buildGameStartPrompt(array $npcs): string
    {
        return sprintf(
            'Commence l\'aventure. Présente brièvement les %d compagnons (%s) et décris la scène d\'ouverture.',
            count($npcs),
            implode(', ', array_column($npcs, 'name'))
        );
    }

    /**
     * Build user prompt for player action
     *
     * @param array $character Player character data
     * @param string $action The action the player wants to take
     * @return string Formatted user prompt
     */
    public function buildPlayerActionPrompt(array $character, string $action): string
    {
        return sprintf(
            "%s fait l'action suivante : %s\n\nRéponds en tant que Maître du Jeu et décris les conséquences. Si nécessaire, demande un jet de dé.",
            $character['name'],
            $action
        );
    }

    /**
     * Build user prompt for dice roll result
     *
     * @param array $character Player character data
     * @param array $diceRoll Dice roll details (type, result, modifier, total, skillCheck)
     * @param string $context Context of the dice roll
     * @return string Formatted user prompt
     */
    public function buildDiceResultPrompt(array $character, array $diceRoll, string $context): string
    {
        return sprintf(
            "%s a lancé %s pour %s.\nRésultat du dé: %d + %d = %d\nContexte: %s\n\nEn tant que Maître du Jeu, décris le résultat de cette action selon le jet de dé.",
            $character['name'],
            $diceRoll['type'] ?? 'd20',
            $diceRoll['skillCheck'] ?? 'une action',
            $diceRoll['result'] ?? 0,
            $diceRoll['modifier'] ?? 0,
            $diceRoll['total'] ?? 0,
            $context
        );
    }

    /**
     * Build system prompt for NPC character
     *
     * @param array $npc NPC character data
     * @return string Formatted system prompt for the NPC
     */
    public function buildNpcSystemPrompt(array $npc): string
    {
        return sprintf(
            "Tu es %s, un personnage %s de classe %s dans un jeu de rôle. Tu dois réagir de manière cohérente avec ton personnage. Réponds en une ou deux phrases courtes comme si tu parlais en tant que ce personnage.",
            $npc['name'],
            $npc['race'] ?? 'inconnu',
            $npc['class'] ?? 'aventurier'
        );
    }

    /**
     * Build user prompt for NPC action
     *
     * @param string $situation Current game situation
     * @return string Formatted user prompt
     */
    public function buildNpcActionPrompt(string $situation): string
    {
        return sprintf(
            "Situation actuelle: %s\n\nComment réagis-tu ou que fais-tu ?",
            $situation
        );
    }
}
