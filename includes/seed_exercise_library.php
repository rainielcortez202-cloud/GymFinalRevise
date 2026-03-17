<?php

function seed_exercise_library(PDO $pdo): array {
    $summary = [
        'muscles_inserted' => 0,
        'exercises_inserted' => 0,
    ];

    $musclesCount = (int)$pdo->query("SELECT COUNT(*) FROM muscles")->fetchColumn();
    $exercisesCount = (int)$pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();
    if ($musclesCount > 0 || $exercisesCount > 0) {
        return $summary;
    }

    $groupRows = $pdo->query("SELECT id, name FROM muscle_groups ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $groupsByName = [];
    foreach ($groupRows as $r) {
        $groupsByName[strtolower(trim((string)$r['name']))] = (int)$r['id'];
    }

    $seed = [
        'chest' => [
            'muscles' => [
                ['name' => 'Pectoralis Major'],
                ['name' => 'Upper Chest (Clavicular Head)'],
            ],
        ],
        'back' => [
            'muscles' => [
                ['name' => 'Latissimus Dorsi'],
                ['name' => 'Trapezius'],
                ['name' => 'Rhomboids'],
                ['name' => 'Erector Spinae'],
            ],
        ],
        'shoulders' => [
            'muscles' => [
                ['name' => 'Anterior Deltoid'],
                ['name' => 'Lateral Deltoid'],
                ['name' => 'Posterior Deltoid'],
                ['name' => 'Rotator Cuff'],
            ],
        ],
        'arms' => [
            'muscles' => [
                ['name' => 'Biceps'],
                ['name' => 'Triceps'],
                ['name' => 'Forearms'],
            ],
        ],
        'legs' => [
            'muscles' => [
                ['name' => 'Quadriceps'],
                ['name' => 'Hamstrings'],
                ['name' => 'Glutes'],
                ['name' => 'Calves'],
            ],
        ],
        'core' => [
            'muscles' => [
                ['name' => 'Rectus Abdominis'],
                ['name' => 'Obliques'],
                ['name' => 'Transverse Abdominis'],
            ],
        ],
    ];

    $defaultExerciseBank = [
        'Pectoralis Major' => [
            ['name' => 'Bench Press', 'video_url' => 'https://www.youtube.com/watch?v=rT7DgCr-3pg', 'image_url' => '', 'description' => 'Keep shoulder blades retracted, feet planted, and control the bar path.'],
            ['name' => 'Dumbbell Press', 'video_url' => 'https://www.youtube.com/watch?v=VmB1G1K7v94', 'image_url' => '', 'description' => 'Press up and slightly in, keep wrists stacked and elbows controlled.'],
        ],
        'Upper Chest (Clavicular Head)' => [
            ['name' => 'Incline Dumbbell Press', 'video_url' => 'https://www.youtube.com/watch?v=8iPEnn-ltC8', 'image_url' => '', 'description' => 'Set bench to a moderate incline and keep elbows slightly tucked.'],
        ],
        'Latissimus Dorsi' => [
            ['name' => 'Lat Pulldown', 'video_url' => 'https://www.youtube.com/watch?v=CAwf7n6Luuc', 'image_url' => '', 'description' => 'Pull elbows down to your sides, avoid swinging, pause at the bottom.'],
            ['name' => 'Pull-Up', 'video_url' => 'https://www.youtube.com/watch?v=eGo4IYlbE5g', 'image_url' => '', 'description' => 'Start from a dead hang, drive elbows down, control the descent.'],
        ],
        'Trapezius' => [
            ['name' => 'Dumbbell Shrugs', 'video_url' => 'https://www.youtube.com/watch?v=cJRVVxmytaM', 'image_url' => '', 'description' => 'Elevate shoulders straight up, pause briefly, avoid rolling.'],
        ],
        'Quadriceps' => [
            ['name' => 'Squat', 'video_url' => 'https://www.youtube.com/watch?v=ultWZbUMPL8', 'image_url' => '', 'description' => 'Brace core, keep knees tracking over toes, control depth.'],
            ['name' => 'Leg Press', 'video_url' => 'https://www.youtube.com/watch?v=IZxyjW7MPJQ', 'image_url' => '', 'description' => 'Control range, don’t lock knees, keep lower back stable.'],
        ],
        'Hamstrings' => [
            ['name' => 'Romanian Deadlift', 'video_url' => 'https://www.youtube.com/watch?v=2SHsk9AzdjA', 'image_url' => '', 'description' => 'Hinge at hips, keep bar close, feel stretch in hamstrings.'],
        ],
        'Glutes' => [
            ['name' => 'Hip Thrust', 'video_url' => 'https://www.youtube.com/watch?v=SEdqd1n0cvg', 'image_url' => '', 'description' => 'Drive through heels, squeeze glutes at top, keep ribs down.'],
        ],
        'Calves' => [
            ['name' => 'Standing Calf Raise', 'video_url' => 'https://www.youtube.com/watch?v=-M4-G8p8fmc', 'image_url' => '', 'description' => 'Full stretch at bottom, pause at top, keep tempo controlled.'],
        ],
        'Anterior Deltoid' => [
            ['name' => 'Overhead Press', 'video_url' => 'https://www.youtube.com/watch?v=2yjwXTZQDDI', 'image_url' => '', 'description' => 'Brace core, press overhead in a straight line, avoid leaning back.'],
        ],
        'Lateral Deltoid' => [
            ['name' => 'Lateral Raises', 'video_url' => 'https://www.youtube.com/watch?v=kDqklk1ZESo', 'image_url' => '', 'description' => 'Raise to shoulder height with control, slight bend in elbows.'],
        ],
        'Posterior Deltoid' => [
            ['name' => 'Rear Delt Fly', 'video_url' => 'https://www.youtube.com/watch?v=EA7u4Q_8HQ0', 'image_url' => '', 'description' => 'Hinge forward, lead with elbows, squeeze rear delts at top.'],
        ],
        'Biceps' => [
            ['name' => 'Dumbbell Curl', 'video_url' => 'https://www.youtube.com/watch?v=ykJmrZ5v0Oo', 'image_url' => '', 'description' => 'Elbows fixed, full range, avoid swinging.'],
        ],
        'Triceps' => [
            ['name' => 'Triceps Pushdown', 'video_url' => 'https://www.youtube.com/watch?v=2-LAMcpzODU', 'image_url' => '', 'description' => 'Elbows pinned, extend fully, control return.'],
        ],
        'Forearms' => [
            ['name' => 'Wrist Curls', 'video_url' => 'https://www.youtube.com/watch?v=3VLTzIrnb5g', 'image_url' => '', 'description' => 'Use a controlled tempo and a full stretch.'],
        ],
        'Rectus Abdominis' => [
            ['name' => 'Crunch', 'video_url' => 'https://www.youtube.com/watch?v=Xyd_fa5zoEU', 'image_url' => '', 'description' => 'Exhale as you crunch, keep lower back controlled.'],
        ],
        'Obliques' => [
            ['name' => 'Side Plank', 'video_url' => 'https://www.youtube.com/watch?v=K2VljzCC16g', 'image_url' => '', 'description' => 'Keep body in a straight line, brace and breathe.'],
        ],
        'Transverse Abdominis' => [
            ['name' => 'Dead Bug', 'video_url' => 'https://www.youtube.com/watch?v=4XLEnwUr1d8', 'image_url' => '', 'description' => 'Keep lower back pressed down, move slowly with control.'],
        ],
    ];

    $pdo->beginTransaction();
    try {
        $insertMuscle = $pdo->prepare("INSERT INTO muscles (muscle_group_id, name, image) VALUES (?, ?, ?)");
        $insertExercise = $pdo->prepare("
            INSERT INTO exercises (muscle_group_id, muscle_id, name, video_url, image_url, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $muscleIdByName = [];

        foreach ($seed as $groupName => $groupData) {
            $gid = $groupsByName[$groupName] ?? null;
            if (!$gid) continue;

            foreach ($groupData['muscles'] as $m) {
                $image = '';
                $insertMuscle->execute([$gid, $m['name'], $image]);
                $mid = (int)$pdo->lastInsertId();
                $muscleIdByName[$m['name']] = ['id' => $mid, 'group_id' => (int)$gid];
                $summary['muscles_inserted']++;
            }
        }

        foreach ($defaultExerciseBank as $muscleName => $exerciseRows) {
            $meta = $muscleIdByName[$muscleName] ?? null;
            if (!$meta) continue;

            foreach ($exerciseRows as $e) {
                $insertExercise->execute([
                    $meta['group_id'],
                    $meta['id'],
                    $e['name'],
                    $e['video_url'],
                    $e['image_url'],
                    $e['description'],
                ]);
                $summary['exercises_inserted']++;
            }
        }

        $pdo->commit();
        return $summary;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

