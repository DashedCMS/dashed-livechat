<?php

return [
    'max_tool_iterations' => 5,
    'history_limit' => 20,
    'poll_interval_ms' => 1500,
    'rate_limit_per_minute' => 15,

    // Max verificatiepogingen voor orderstatus per conversatie.
    'order_lookup_max_attempts' => 5,

    // Tijdzone van de vestiging voor openingstijden-evaluatie.
    'timezone' => 'Europe/Amsterdam',

    // Vat oudere berichten samen zodra een gesprek meer dan dit aantal berichten heeft.
    'summarize_after' => 30,
    // Aantal recente berichten dat altijd integraal meegaat.
    'summary_keep_recent' => 10,
    // Model voor samenvattingen (goedkoop).
    'summary_model' => 'claude-haiku-4-5-20251001',

    // Ruwe kostenschatting (USD per miljoen tokens).
    'cost_per_million_input' => 3.0,
    'cost_per_million_output' => 15.0,

    // SSE token-streaming voor de widget (opt-in, default uit zodat fase-1 polling-tests groen blijven).
    'streaming' => false,
];
