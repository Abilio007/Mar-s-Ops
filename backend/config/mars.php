<?php

return [
    'mission_types' => [
        ['value' => 'scientific', 'label' => 'Científica'],
        ['value' => 'reconnaissance', 'label' => 'Reconhecimento'],
        ['value' => 'colonization', 'label' => 'Colonização'],
        ['value' => 'rescue', 'label' => 'Resgate'],
        ['value' => 'mining', 'label' => 'Mineração'],
    ],
    'risk_tolerances' => [
        ['value' => 'low', 'label' => 'Baixo'],
        ['value' => 'moderate', 'label' => 'Moderado'],
        ['value' => 'high', 'label' => 'Elevado'],
    ],
    'equipment_by_mission_type' => [
        'scientific' => ['Espectrómetro Raman portátil', 'Perfurador geológico de baixa vibração'],
        'reconnaissance' => ['Drone batedor', 'Radar de penetração no solo (GPR)'],
        'colonization' => ['Módulo inflável de abrigo térmico', 'Unidade compacta de extração de gelo'],
        'rescue' => ['Maca pressurizada de transporte', 'Balizas de localização de emergência'],
        'mining' => ['Analisador de regolito e minerais', 'Contentor selado de amostras'],
    ],
    'cruise_speed_km_per_hour' => 85,
];
