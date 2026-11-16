// Muted colors with separate hues for the observation types.
const colors = Object.freeze({
    Unspecified: '#8a8a8a',
    Scar: '#776c5b',
    Tattoo: '#375f94',
    Birthmark: '#a15d8f',
    Injury: '#a56532',
    'Gunshot wound': '#70324e',
    'Stab wound': '#397864',
    Bruise: '#7b6a9d',
    Abrasion: '#b39a55',
    Other: '#70783c',
});

export function observationType(type) {
    return typeof type === 'string' && type.trim() ? type : 'Unspecified';
}

export function observationStyle(type) {
    const label = observationType(type);
    if (Object.hasOwn(colors, label)) {
        return {
            '--he-observation-color': colors[label],
            '--he-observation-foreground': '#fff',
        };
    }

    // Host-defined types keep the same color across views, ordering, and reloads.
    let hash = 2166136261;
    for (const character of label) hash = Math.imul(hash ^ character.codePointAt(0), 16777619) >>> 0;
    return {
        '--he-observation-color': `hsl(${hash % 360} 55% 72%)`,
        '--he-observation-foreground': '#171717',
    };
}
