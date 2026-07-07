<?php

return [
    'max_tool_iterations' => 5,
    'history_limit' => 20,
    'poll_interval_ms' => 1500,
    'rate_limit_per_minute' => 15,

    // Bezoeker-aanwezigheid (chat-overzicht/CMS/app): 'active' zolang de
    // widget-voorgrond-poll binnen dit venster verse activiteit toont.
    'presence_active_seconds' => 45,
    // Daarbuiten 'idle' zolang de site-brede presence-beacon nog binnen dit
    // venster pingt (tab op de achtergrond, nog op de site); anders 'away'.
    'presence_away_seconds' => 120,

    // Vraag de bezoeker om e-mail na zoveel seconden zonder nieuw bericht (inactiviteit).
    'ask_email_after_seconds' => 15,
    // Beschouw de bezoeker als 'offline' als de laatste activiteit ouder is dan dit;
    // agent-/AI-antwoorden gaan dan als e-mail (met hervat-knop) naar de bezoeker.
    'offline_reply_after_seconds' => 30,

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

    // Ruwe kostenschatting (USD per miljoen tokens; Anthropic rekent in USD).
    'cost_per_million_input' => 3.0,
    'cost_per_million_output' => 15.0,
    // Wisselkoers USD -> EUR voor de weergegeven kostenschatting.
    'usd_to_eur' => 0.92,

    // SSE token-streaming voor de widget (opt-in, default uit zodat fase-1 polling-tests groen blijven).
    'streaming' => false,
];
