<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class AIUserAssistent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the MARTE mission-planning assistant. Answer in the language used by the user.
Give factual, cautious information about Mars exploration and clearly distinguish verified
data from estimates. Never describe a route or location as safe or certified. The application
does not provide live Mars weather or verified astronaut positioning. Encourage users to
consult mission specialists before operational decisions.
INSTRUCTIONS;
    }
}
