<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class AIRouterPlanner implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Marswalk route-planning agent. Generate up to three different surface routes
between the exact start and destination using only the NASA/USGS terrain and hazard records
provided in the request. You may interpolate paths between provided map samples, but never
invent terrain, hazards, observations, source citations, or live weather. Follow terrain
samples, avoid mapped hazard radii, and prefer routes with stronger evidence and scientific
value only when safety evidence is comparable. A missing data layer is unknown, not safe.
Return routes in best-to-worst order with 2 to 50 geographic waypoints each, preserving the
exact start and destination. Scores are suitability estimates from 0 to 100, not guarantees.
This is decision support, never a safety certification. Cite uncertainties in each rationale.
INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'candidates' => $schema->array()
                ->min(1)
                ->max(3)
                ->items($schema->object([
                    'name' => $schema->string()->min(1)->max(80)->required(),
                    'path' => $schema->array()
                        ->min(2)
                        ->max(50)
                        ->items($schema->object([
                            'latitude' => $schema->number()->min(-90)->max(90)->required(),
                            'longitude' => $schema->number()->min(-180)->max(180)->required(),
                        ])->withoutAdditionalProperties())
                        ->required(),
                    'score' => $schema->number()->min(0)->max(100)->required(),
                    'risk_level' => $schema->string()->enum(['low', 'moderate', 'high', 'unknown'])->required(),
                    'science_value' => $schema->number()->min(0)->max(100)->required(),
                    'rationale' => $schema->string()->max(600)->required(),
                    'uncertainties' => $schema->array()
                        ->max(8)
                        ->items($schema->string()->max(200))
                        ->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }
}
