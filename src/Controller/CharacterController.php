<?php

namespace App\Controller;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CharacterController extends AbstractController
{
    #[Route('/api/characters', name: 'api_characters_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation des champs requis
        $requiredFields = ['name', 'race', 'class', 'players', 'lvl'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'message' => "Le champ '$field' est requis"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $character = new Character();
        $character->setName($data['name']);
        $character->setRace($data['race']);
        $character->setClass($data['class']);
        $character->setPlayers((int)$data['players']);
        $character->setLvl((int)$data['lvl']);

        // Gérer les statistiques si elles sont présentes
        if (isset($data['statistic']) && is_array($data['statistic'])) {
            $character->setStatistic($data['statistic']);
        } else {
            // Initialiser avec des statistiques par défaut si non fournies
            $character->setStatistic([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10
            ]);
        }

        // Validation
        $errors = $validator->validate($character);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Erreur de validation',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($character);
        $entityManager->flush();

        return $this->json([
            'message' => 'Personnage créé avec succès',
            'character' => [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'race' => $character->getRace(),
                'class' => $character->getClass(),
                'players' => $character->getPlayers(),
                'lvl' => $character->getLvl(),
                'statistic' => $character->getStatistic()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/characters', name: 'api_characters_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        $characters = $entityManager->getRepository(Character::class)->findAll();

        $charactersList = [];
        foreach ($characters as $character) {
            $charactersList[] = [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'race' => $character->getRace(),
                'class' => $character->getClass(),
                'players' => $character->getPlayers(),
                'lvl' => $character->getLvl(),
                'statistic' => $character->getStatistic()
            ];
        }

        return $this->json($charactersList);
    }

    #[Route('/api/characters/{id}', name: 'api_characters_get', methods: ['GET'])]
    public function get(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $character = $entityManager->getRepository(Character::class)->find($id);

        if (!$character) {
            return $this->json([
                'message' => 'Personnage non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $character->getId(),
            'name' => $character->getName(),
            'race' => $character->getRace(),
            'class' => $character->getClass(),
            'players' => $character->getPlayers(),
            'lvl' => $character->getLvl(),
            'statistic' => $character->getStatistic()
        ]);
    }

    #[Route('/api/characters/{id}', name: 'api_characters_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $character = $entityManager->getRepository(Character::class)->find($id);

        if (!$character) {
            return $this->json([
                'message' => 'Personnage non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs si présents
        if (isset($data['name'])) {
            $character->setName($data['name']);
        }
        if (isset($data['race'])) {
            $character->setRace($data['race']);
        }
        if (isset($data['class'])) {
            $character->setClass($data['class']);
        }
        if (isset($data['players'])) {
            $character->setPlayers((int)$data['players']);
        }
        if (isset($data['lvl'])) {
            $character->setLvl((int)$data['lvl']);
        }
        if (isset($data['statistic']) && is_array($data['statistic'])) {
            $character->setStatistic($data['statistic']);
        }

        // Validation
        $errors = $validator->validate($character);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Erreur de validation',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Personnage mis à jour avec succès',
            'character' => [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'race' => $character->getRace(),
                'class' => $character->getClass(),
                'players' => $character->getPlayers(),
                'lvl' => $character->getLvl(),
                'statistic' => $character->getStatistic()
            ]
        ]);
    }

    #[Route('/api/characters/{id}', name: 'api_characters_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $character = $entityManager->getRepository(Character::class)->find($id);

        if (!$character) {
            return $this->json([
                'message' => 'Personnage non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($character);
        $entityManager->flush();

        return $this->json([
            'message' => 'Personnage supprimé avec succès'
        ]);
    }
}
