<?php

namespace App\Service;

/**
 * Service responsible for generating Non-Player Characters (NPCs) for the game
 *
 * This service creates randomized NPCs with unique race/class combinations,
 * appropriate names, and personality traits based on their class.
 */
class NpcGeneratorService
{
    // Available character races
    private const RACES = ['Elfe', 'Nain', 'Humain', 'Halfelin', 'Demi-Elfe', 'Tiefling'];

    // Available character classes
    private const CLASSES = ['Guerrier', 'Magicien', 'Roublard', 'Clerc', 'Rôdeur', 'Paladin', 'Barde', 'Druide'];

    // Pre-defined names organized by race for better immersion
    private const NAMES_BY_RACE = [
        'Elfe' => ['Elara', 'Thranduil', 'Galadriel', 'Legolas', 'Arwen'],
        'Nain' => ['Thorin', 'Gimli', 'Balin', 'Dwalin', 'Dori'],
        'Humain' => ['Aragorn', 'Boromir', 'Éowyn', 'Faramir', 'Théoden'],
        'Halfelin' => ['Bilbo', 'Frodo', 'Sam', 'Merry', 'Pippin'],
        'Demi-Elfe' => ['Elrond', 'Elladan', 'Elrohir', 'Estel'],
        'Tiefling' => ['Zariel', 'Moloch', 'Levistus', 'Glasya']
    ];

    // Personality traits mapped to character classes
    private const PERSONALITIES = [
        'Guerrier' => ['brave', 'loyal', 'protecteur', 'direct'],
        'Magicien' => ['intellectuel', 'curieux', 'prudent', 'mystérieux'],
        'Roublard' => ['rusé', 'agile', 'cynique', 'opportuniste'],
        'Clerc' => ['pieux', 'compatissant', 'sage', 'dévoué'],
        'Rôdeur' => ['indépendant', 'silencieux', 'observateur', 'proche de la nature'],
        'Paladin' => ['honorable', 'juste', 'déterminé', 'charismatique'],
        'Barde' => ['charmant', 'créatif', 'sociable', 'optimiste'],
        'Druide' => ['sage', 'pacifique', 'mystique', 'en harmonie avec la nature']
    ];

    // Name suffixes for avoiding duplicates
    private const NAME_SUFFIXES = ['le Brave', 'le Sage', 'l\'Ancien', 'le Jeune', 'le Rapide'];

    /**
     * Generate a specified number of NPCs for the party
     *
     * @param array $playerCharacter The player's character data (used for level matching)
     * @param int $count Number of NPCs to generate
     * @return array Array of generated NPCs with their properties
     */
    public function generateNPCs(array $playerCharacter, int $count): array
    {
        // No NPCs needed if count is 0 or negative
        if ($count <= 0) {
            return [];
        }

        $npcs = [];
        $usedCombinations = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate unique race/class combination to avoid duplicates
            do {
                $race = self::RACES[array_rand(self::RACES)];
                $class = self::CLASSES[array_rand(self::CLASSES)];
                $combination = "$race-$class";
            } while (in_array($combination, $usedCombinations));

            $usedCombinations[] = $combination;

            // Select appropriate name based on race
            $name = $this->generateUniqueName($race, $npcs);

            // Build NPC data structure
            $npcs[] = [
                'name' => $name,
                'race' => $race,
                'class' => $class,
                'personality' => $this->generatePersonality($class),
                'level' => $playerCharacter['level'] ?? 1
            ];
        }

        return $npcs;
    }

    /**
     * Generate a unique name for an NPC based on their race
     *
     * Ensures no duplicate names by adding suffixes if necessary
     *
     * @param string $race The race of the NPC
     * @param array $existingNpcs Already generated NPCs to check for duplicates
     * @return string A unique name for the NPC
     */
    private function generateUniqueName(string $race, array $existingNpcs): string
    {
        // Get appropriate names for the race, fallback to generic name
        $names = self::NAMES_BY_RACE[$race] ?? ['Compagnon'];
        $baseName = $names[array_rand($names)];
        $name = $baseName;

        // Add suffix if name already exists
        $counter = 1;
        $existingNames = array_column($existingNpcs, 'name');

        while (in_array($name, $existingNames)) {
            $suffixIndex = ($counter - 1) % count(self::NAME_SUFFIXES);
            $name = $baseName . ' ' . self::NAME_SUFFIXES[$suffixIndex];
            $counter++;
        }

        return $name;
    }

    /**
     * Generate a personality trait for an NPC based on their class
     *
     * @param string $class The character class
     * @return string A personality trait appropriate for the class
     */
    private function generatePersonality(string $class): string
    {
        $traits = self::PERSONALITIES[$class] ?? ['équilibré'];
        return $traits[array_rand($traits)];
    }
}
