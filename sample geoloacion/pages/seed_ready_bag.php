<?php
// One-time helper script to seed ready_bag_templates with common advice.
// Run this once after creating the database (e.g. via browser or CLI),
// then you can delete or protect this file.

require_once __DIR__ . '/db.php';

$pdo = db();

$rows = [
    // Typhoon levels
    ['typhoon', 1, 1, 'Level 1 typhoon – light rain', "A low-level typhoon is approaching.\n\n- Bring an umbrella and raincoat when going outside.\n- Secure lightweight objects around your home.\n- Monitor MDRRMO announcements for updates."],
    ['typhoon', 2, 3, 'Moderate typhoon – be prepared', "A moderate typhoon is expected.\n\n- Prepare your go bag (water, food, flashlights, medicines, important documents).\n- Charge mobile phones and power banks.\n- Identify the nearest evacuation center in case water rises."],
    ['typhoon', 4, 5, 'Severe typhoon – high risk', "A severe typhoon is affecting San Ildefonso.\n\n- Keep your go bag ready near the door.\n- Stay indoors away from windows and glass.\n- Be ready to evacuate immediately when instructed by authorities.\n- Check on seniors, PWDs, and children in your household."],

    // Flood
    ['flood', 1, 2, 'Flood watch – monitor water level', "Low to moderate flooding is possible.\n\n- Monitor canals and rivers near your area.\n- Move valuables to higher shelves.\n- Avoid walking or driving through floodwater if possible."],
    ['flood', 3, 5, 'Severe flooding – possible evacuation', "Severe flooding is expected or ongoing.\n\n- Keep your go bag and important documents in a waterproof container.\n- Disconnect electrical appliances if water is entering your home.\n- Follow MDRRMO instructions on when and where to evacuate."],

    // Heat / high heat index
    ['heat', 1, 1, 'Warm weather – stay comfortable', "Weather is warm.\n\n- Drink water regularly.\n- Use light clothing.\n- Avoid staying long in direct sunlight."],
    ['heat', 2, 3, 'High heat index – take precautions', "Heat index is high.\n\n- Drink more water than usual; avoid sugary drinks and alcohol.\n- Limit time outdoors during midday.\n- Check on children, seniors, and PWDs for signs of heat stress."],
    ['heat', 4, 5, 'Extreme heat – health risk', "Heat index is at an extreme level.\n\n- Stay indoors in cool, shaded, or air‑conditioned areas as much as possible.\n- Postpone outdoor activities.\n- Immediately seek medical help if someone feels dizzy, confused, or faints."],

    // Earthquake (general guidance)
    ['earthquake', 1, 5, 'Earthquake preparedness', "Earthquakes can happen without warning.\n\n- Secure heavy furniture and appliances.\n- Know safe spots to \"Drop, Cover, and Hold On\" inside your home.\n- Prepare a go bag with essentials in case evacuation is needed."],

    // General
    ['general', 1, 5, 'General emergency go bag', "For any emergency, prepare a go bag with:\n\n- Drinking water and ready‑to‑eat food\n- Flashlight and extra batteries\n- Basic medicines and first‑aid kit\n- Extra clothes and blanket\n- Copies of important documents (IDs, medical records)\n- Whistle, face masks, and hygiene items."]
];

$stmt = $pdo->prepare("INSERT INTO ready_bag_templates (disaster_type, level_min, level_max, title, message)
                       VALUES (?, ?, ?, ?, ?)");

foreach ($rows as $r) {
    $stmt->execute($r);
}

echo "Seeded ready_bag_templates with " . count($rows) . " entries.\n";

