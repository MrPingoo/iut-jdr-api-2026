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
        $characterName = $character['name'] ?? 'Aventurier';

        // Calculer les PV max basés sur le niveau (15 PV au niveau 1, 35 PV au niveau 20)
        $level = $character['level'] ?? 1;
        $maxHp = $this->calculateMaxHp($level);

        return sprintf(
            "Tu es un Maître du Jeu expert dans Donjons & Dragons 5e. Tu guides une aventure épique dans le monde de %s.

Le personnage principal joué par l'utilisateur :
- Nom: %s
- Race: %s
- Classe: %s
- Niveau: %d
- PV Maximum: %d
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

**GESTION DES POINTS DE VIE (PV) :**
- Le personnage %s possède %d PV maximum (niveau %d)
- Les compagnons ont aussi des PV basés sur leur niveau
- Lors de combats ou situations dangereuses, tu DOIS indiquer les changements de PV
- Format OBLIGATOIRE pour les changements de PV : utilise une ligne spéciale avec le format JSON suivant :
  [HP_CHANGE] {\"character\": \"NomDuPersonnage\", \"change\": -5, \"reason\": \"Coup d'épée gobeline\"}

  Exemples de changements de PV :
  [HP_CHANGE] {\"character\": \"%s\", \"change\": -8, \"reason\": \"Attaque de dragon\"}
  [HP_CHANGE] {\"character\": \"Elara\", \"change\": -3, \"reason\": \"Flèche empoisonnée\"}
  [HP_CHANGE] {\"character\": \"%s\", \"change\": 10, \"reason\": \"Potion de soin\"}
  [HP_CHANGE] {\"character\": \"Thorin\", \"change\": 15, \"reason\": \"Repos long\"}

- Les changements négatifs représentent des dégâts (-1 à -20 selon la gravité)
- Les changements positifs représentent des soins (+1 à +20)
- Sois logique : une égratignure = -1 à -3 PV, une attaque sérieuse = -5 à -10 PV, une attaque critique = -15 à -20 PV
- Lors d'un combat réussi (bon jet de dé), le joueur peut infliger des dégâts à l'ennemi SANS perdre de PV
- Lors d'un combat échoué (mauvais jet), le joueur subit des dégâts
- Les compagnons peuvent aussi subir/infliger des dégâts selon la situation
- Si un personnage atteint 0 PV, indique qu'il est inconscient ou gravement blessé

**GESTION DE L'EXPÉRIENCE (XP) ET DES NIVEAUX :**
- Les personnages gagnent de l'XP en accomplissant des exploits (combats, quêtes, découvertes, etc.)
- Tu DOIS accorder de l'XP après chaque action réussie, victoire ou accomplissement significatif
- Format OBLIGATOIRE pour les gains d'XP :
  [XP_GAIN] {\"character\": \"NomDuPersonnage\", \"xp\": 50, \"reason\": \"Victoire contre un gobelin\"}

  Exemples de gains d'XP :
  [XP_GAIN] {\"character\": \"%s\", \"xp\": 100, \"reason\": \"Victoire contre un dragon\"}
  [XP_GAIN] {\"character\": \"Elara\", \"xp\": 50, \"reason\": \"Résolution d'énigme\"}
  [XP_GAIN] {\"character\": \"%s\", \"xp\": 25, \"reason\": \"Exploration de donjon\"}
  [XP_GAIN] {\"character\": \"Thorin\", \"xp\": 75, \"reason\": \"Sauvetage d'un villageois\"}

- Barème des gains d'XP :
  * Action mineure (exploration, interaction) : 10-25 XP
  * Combat facile (gobelins, loups) : 30-50 XP
  * Combat moyen (orcs, trolls) : 50-100 XP
  * Combat difficile (dragon, démon) : 100-200 XP
  * Résolution de quête : 50-150 XP
  * Découverte importante : 25-75 XP
  * Acte héroïque : 100-300 XP

- Accorde de l'XP à TOUS les personnages impliqués dans l'action (joueur + compagnons actifs)
- Le joueur passe automatiquement de niveau quand il atteint l'XP requise
- Lors d'un passage de niveau, les PV sont restaurés au maximum
- Sois généreux avec l'XP pour encourager la progression

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
            $characterName,
            $character['race'] ?? 'Humain',
            $character['class'] ?? 'Guerrier',
            $level,
            $maxHp,
            $stats['strength'] ?? 10,
            $stats['constitution'] ?? 10,
            $stats['intelligence'] ?? 10,
            $stats['wisdom'] ?? 10,
            $stats['dexterity'] ?? 10,
            $stats['charisma'] ?? 10,
            $npcsList,
            $playerCount,
            $characterName,  // PV section
            $maxHp,          // PV section
            $level,          // PV section
            $characterName,  // HP_CHANGE exemple 1
            $characterName,  // HP_CHANGE exemple 2
            $characterName,  // XP_GAIN exemple 1
            $characterName   // XP_GAIN exemple 2
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
     * @param array $context Game context including HP
     * @return string Formatted user prompt
     */
    public function buildPlayerActionPrompt(array $character, string $action, array $context = []): string
    {
        $hpInfo = '';

        // Ajouter les informations de PV si disponibles
        if (isset($context['characterHp'])) {
            $maxHp = $context['characterMaxHp'] ?? 35;
            $hpInfo .= sprintf("\nPV actuels de %s : %d/%d", $character['name'], $context['characterHp'], $maxHp);
        }

        if (isset($context['companionsHp']) && !empty($context['companionsHp'])) {
            $hpInfo .= "\nPV des compagnons :";
            foreach ($context['companionsHp'] as $companion) {
                $maxHp = $companion['maxHp'] ?? 35;
                $hpInfo .= sprintf("\n- %s : %d/%d", $companion['name'], $companion['hp'], $maxHp);
            }
        }

        return sprintf(
            "%s fait l'action suivante : %s%s\n\nRéponds en tant que Maître du Jeu et décris les conséquences. Si nécessaire, demande un jet de dé. Si l'action implique un combat ou un danger, indique les changements de PV avec le format [HP_CHANGE].",
            $character['name'],
            $action,
            $hpInfo
        );
    }

    /**
     * Build user prompt for dice roll result
     *
     * @param array $character Player character data
     * @param array $diceRoll Dice roll details (type, result, modifier, total, skillCheck)
     * @param string $context Context of the dice roll
     * @param array $gameContext Game context including HP
     * @return string Formatted user prompt
     */
    public function buildDiceResultPrompt(array $character, array $diceRoll, string $context, array $gameContext = []): string
    {
        $hpInfo = '';

        // Ajouter les informations de PV si disponibles
        if (isset($gameContext['characterHp'])) {
            $maxHp = $gameContext['characterMaxHp'] ?? 35;
            $hpInfo .= sprintf("\nPV actuels de %s : %d/%d", $character['name'], $gameContext['characterHp'], $maxHp);
        }

        if (isset($gameContext['companionsHp']) && !empty($gameContext['companionsHp'])) {
            $hpInfo .= "\nPV des compagnons :";
            foreach ($gameContext['companionsHp'] as $companion) {
                $maxHp = $companion['maxHp'] ?? 35;
                $hpInfo .= sprintf("\n- %s : %d/%d", $companion['name'], $companion['hp'], $maxHp);
            }
        }

        return sprintf(
            "%s a lancé %s pour %s.\nRésultat du dé: %d + %d = %d\nContexte: %s%s\n\nEn tant que Maître du Jeu, décris le résultat de cette action selon le jet de dé. Si l'action réussie/échoue implique des dégâts ou des soins, indique les changements de PV avec le format [HP_CHANGE].",
            $character['name'],
            $diceRoll['type'] ?? 'd20',
            $diceRoll['skillCheck'] ?? 'une action',
            $diceRoll['result'] ?? 0,
            $diceRoll['modifier'] ?? 0,
            $diceRoll['total'] ?? 0,
            $context,
            $hpInfo
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

    /**
     * Calculate maximum HP based on character level
     * Level 1: 15 HP
     * Level 20: 35 HP
     * Linear progression between levels
     *
     * @param int $level Character level (1-20)
     * @return int Maximum HP
     */
    private function calculateMaxHp(int $level): int
    {
        // Assurer que le niveau est entre 1 et 20
        $level = max(1, min(20, $level));

        // Formule linéaire : HP = 15 + ((level - 1) * 20 / 19)
        // Niveau 1: 15 + 0 = 15 PV
        // Niveau 20: 15 + (19 * 20 / 19) = 15 + 20 = 35 PV
        $baseHp = 15;
        $hpPerLevel = 20 / 19; // Progression de 20 PV sur 19 niveaux

        return (int) round($baseHp + (($level - 1) * $hpPerLevel));
    }
}

