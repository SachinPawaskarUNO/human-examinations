// IDs are persisted as sketch_id. Keep them independent of asset filenames and build hashes.
export const sketchGroups = [
    {
        id: 'body',
        label: 'Full body',
        views: [
            {
                id: 'body-front',
                label: 'Body · Front (anterior)',
                width: 150.23619,
                height: 360.6836,
                sides: ['Right', 'Left'],
                src: new URL('../sketches/interpol-dvi-body-front.svg', import.meta.url).href,
            },
            {
                id: 'body-back',
                label: 'Body · Back (posterior)',
                width: 140.41522,
                height: 362.97328,
                sides: ['Left', 'Right'],
                src: new URL('../sketches/interpol-dvi-body-back.svg', import.meta.url).href,
            },
        ],
    },
    {
        id: 'head',
        label: 'Head',
        views: [
            {
                id: 'head-front',
                label: 'Head · Front',
                width: 83.15648,
                height: 127.20618,
                src: new URL('../sketches/interpol-dvi-head-front.svg', import.meta.url).href,
            },
            {
                id: 'head-back',
                label: 'Head · Back',
                width: 83.21924,
                height: 125.892948,
                src: new URL('../sketches/interpol-dvi-head-back.svg', import.meta.url).href,
            },
            {
                id: 'head-left-profile',
                label: 'Head · Left profile',
                width: 97.844058,
                height: 116.67346,
                src: new URL('../sketches/interpol-dvi-head-left-profile.svg', import.meta.url).href,
            },
            {
                id: 'head-right-profile',
                label: 'Head · Right profile',
                width: 97.84387,
                height: 116.67346,
                src: new URL('../sketches/interpol-dvi-head-right-profile.svg', import.meta.url).href,
            },
            {
                id: 'head-top',
                label: 'Head · Top',
                width: 90.99957,
                height: 128.61804,
                src: new URL('../sketches/interpol-dvi-head-top.svg', import.meta.url).href,
            },
        ],
    },
    {
        id: 'hands',
        label: 'Hands',
        views: [
            {
                id: 'hand-left-palmar',
                label: 'Left hand · Palmar',
                width: 74.13826,
                height: 125.732547,
                src: new URL('../sketches/interpol-dvi-hand-left-palmar.svg', import.meta.url).href,
            },
            {
                id: 'hand-right-palmar',
                label: 'Right hand · Palmar',
                width: 69.50037,
                height: 116.73767,
                src: new URL('../sketches/interpol-dvi-hand-right-palmar.svg', import.meta.url).href,
            },
            {
                id: 'hand-left-dorsal',
                label: 'Left hand · Dorsal',
                width: 83.48901,
                height: 121.09192,
                src: new URL('../sketches/interpol-dvi-hand-left-dorsal.svg', import.meta.url).href,
            },
            {
                id: 'hand-right-dorsal',
                label: 'Right hand · Dorsal',
                width: 81.58928,
                height: 116.38037,
                src: new URL('../sketches/interpol-dvi-hand-right-dorsal.svg', import.meta.url).href,
            },
        ],
    },
    {
        id: 'feet',
        label: 'Feet',
        views: [
            {
                id: 'foot-left-dorsal',
                label: 'Left foot · Dorsal',
                width: 42.07727,
                height: 104.815677,
                src: new URL('../sketches/interpol-dvi-foot-left-dorsal.svg', import.meta.url).href,
            },
            {
                id: 'foot-right-dorsal',
                label: 'Right foot · Dorsal',
                width: 42.077058,
                height: 104.81543,
                src: new URL('../sketches/interpol-dvi-foot-right-dorsal.svg', import.meta.url).href,
            },
            {
                id: 'foot-left-plantar',
                label: 'Left foot · Plantar',
                width: 41.664247,
                height: 104.221927,
                src: new URL('../sketches/interpol-dvi-foot-left-plantar.svg', import.meta.url).href,
            },
            {
                id: 'foot-right-plantar',
                label: 'Right foot · Plantar',
                width: 41.66449,
                height: 104.22217,
                src: new URL('../sketches/interpol-dvi-foot-right-plantar.svg', import.meta.url).href,
            },
        ],
    },
];

export const sketches = sketchGroups.flatMap((group) => group.views);
