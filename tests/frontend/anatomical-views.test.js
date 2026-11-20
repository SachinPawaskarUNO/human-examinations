import assert from 'node:assert/strict';
import {after, before, test} from 'node:test';
import vue from '@vitejs/plugin-vue';
import {createSSRApp, h} from 'vue';
import {renderToString} from 'vue/server-renderer';
import {createServer} from 'vite';
import {sketches} from '../../resources/js/sketches.js';

let server;
let AnatomicalViews;
let Workspace;
let RegionCombobox;

before(async () => {
    server = await createServer({
        configFile: false,
        plugins: [vue()],
        optimizeDeps: {noDiscovery: true},
        server: {middlewareMode: true, hmr: false, watch: null},
    });
    AnatomicalViews = (await server.ssrLoadModule('/resources/js/components/AnatomicalViews.vue')).default;
    Workspace = (await server.ssrLoadModule('/resources/js/components/HumanExaminationWorkspace.vue')).default;
    RegionCombobox = (await server.ssrLoadModule('/resources/js/components/RegionCombobox.vue')).default;
});

after(async () => {
    await server?.close();
});

function render(component, props = {}, slots = {}) {
    return renderToString(createSSRApp({render: () => h(component, props, slots)}));
}

test('the region combobox retains custom text and labels its editable input and dropdown', async () => {
    const html = await render(RegionCombobox, {
        id: 'test-region', name: 'region', modelValue: 'Custom precise location',
        options: ['Head', 'Right upper arm'], 'aria-describedby': 'region-help',
    });
    assert.match(html, /<input\b[^>]*id="test-region"/);
    assert.match(html, /name="region"/);
    assert.match(html, /role="combobox"/);
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, /aria-describedby="region-help"/);
    assert.match(html, /value="Custom precise location"/);
    assert.match(html, /aria-label="Show region choices"/);
    assert.doesNotMatch(html, /<datalist\b|\slist="/);

    const disabled = await render(RegionCombobox, {disabled: true});
    assert.match(disabled, /<input\b[^>]*disabled/);
    assert.match(disabled, /<button\b[^>]*disabled/);
});

test('each region combobox has a separate listbox ID', async () => {
    const html = await render({render: () => h('div', [h(RegionCombobox), h(RegionCombobox)])});
    const ids = [...html.matchAll(/<input\b[^>]*aria-controls="([^"]+)"/g)].map((match) => match[1]);
    assert.equal(ids.length, 2);
    assert.equal(new Set(ids).size, 2);
});

test('the default view offers category tabs and both body diagrams', async () => {
    const html = await render(AnatomicalViews);

    assert.match(html, /role="tablist"[^>]*aria-label="Anatomical views"/);
    assert.equal([...html.matchAll(/role="tab"/g)].length, 4);
    for (const group of ['Full body', 'Head', 'Hands', 'Feet']) assert.ok(html.includes(group));
    assert.match(html, /data-group="body"[^>]*aria-selected="true"/);
    assert.match(html, /role="tabpanel"/);
    assert.doesNotMatch(html, /<select\b|<optgroup\b/);
    assert.match(html, /data-sketch="body-front"/);
    assert.match(html, /data-sketch="body-back"/);
    assert.equal([...html.matchAll(/<img\b/g)].length, 2);
    assert.match(html, /<img\b[^>]*alt="[^"]+anatomical sketch"/);
    assert.match(html, /Anatomical left and right refer to the subject\./);
});

test('anatomy offers zoom, reset, height, and keyboard-accessible viewport controls', async () => {
    const html = await render(AnatomicalViews);
    for (const label of ['Zoom in', 'Zoom out', 'Reset view', 'View height']) {
        assert.ok(html.includes(`aria-label="${label}"`), label);
    }
    assert.match(html, /100%/);
    assert.match(html, /type="range"[^>]*min="280"[^>]*max="900"/);
    assert.equal([...html.matchAll(/data-viewport="body-(?:front|back)"/g)].length, 2);
    assert.match(html, /Scroll to zoom/);
    assert.match(html, /right-drag/);
    for (const id of ['body-front', 'body-back']) {
        const figure = html.match(new RegExp(`<figure\\b[^>]*data-sketch="${id}"[^>]*>[\\s\\S]*?<\\/figure>`))[0];
        assert.match(figure, /zoom controls/);
        assert.match(figure, /aria-label="Zoom in"/);
        assert.match(figure, /aria-label="Zoom out"/);
        assert.match(figure, /aria-label="Reset view"/);
        assert.match(figure, /<circle\b/);
    }
    assert.equal([...html.matchAll(/aria-label="Zoom level"/g)].length, 2);
    assert.equal([...html.matchAll(/aria-label="View height"/g)].length, 1);

    const disabled = await render(AnatomicalViews, {disabled: true});
    for (const label of ['Zoom in', 'Zoom out', 'Reset view', 'View height']) {
        assert.match(disabled, new RegExp(`<(?:button|input)\\b[^>]*aria-label="${label}"[^>]*disabled`));
    }
});

test('each stable view ID selects the corresponding diagram and aspect ratio', async () => {
    for (const sketch of sketches) {
        const html = await render(AnatomicalViews, {view: sketch.id});

        assert.ok(html.includes(`data-sketch="${sketch.id}"`), sketch.id);
        assert.ok(html.includes(`src="${sketch.src}"`), sketch.id);
        assert.ok(html.includes(`--he-sketch-ratio:${sketch.width / sketch.height}`), sketch.id);
    }
});

test('density mode replaces pins with mapped-only heatmaps and a density legend', async () => {
    const observations = [
        {number: 1, type: 'Scar', sketch_id: 'body-front', x: 0.5, y: 0.4},
        {number: 2, type: 'Tattoo', sketch_id: 'body-back', x: 0.5, y: 0.3},
        {number: 3, type: 'Scar'},
    ];
    const html = await render(AnatomicalViews, {mode: 'density', placementEnabled: true, observations, draftLocation: {sketch_id: 'body-front', x: 0.4, y: 0.5}});
    assert.match(html, /aria-label="Density"[^>]*aria-pressed="true"/);
    assert.match(html, /data-heatmap="body-front"/);
    assert.match(html, /data-heatmap="body-back"/);
    assert.match(html, /2 mapped observations/);
    assert.match(html, /Relative density/);
    assert.doesNotMatch(html, /data-observation=|data-draft-marker|Click inside an anatomical outline/);
    assert.match(html, /zoom controls/);
    const empty = await render(AnatomicalViews, {mode: 'density'});
    assert.match(empty, /No mapped observations in these views/);
    const locked = await render(AnatomicalViews, {disabled: true});
    assert.match(locked, /aria-label="Density"[^>]*disabled/);
});

test('an unknown view ID falls back to the paired body diagrams', async () => {
    const html = await render(AnatomicalViews, {view: 'not-a-sketch'});

    assert.match(html, /data-sketch="body-front"/);
    assert.match(html, /data-sketch="body-back"/);
    assert.ok(html.includes(`src="${sketches[0].src}"`));
});

test('stable view IDs display all the sketches in their selected category', async () => {
    for (const view of ['body-front', 'body-back']) {
        const html = await render(AnatomicalViews, {view});
        assert.equal([...html.matchAll(/<img\b/g)].length, 2);
        assert.match(html, /data-sketch="body-front"/);
        assert.match(html, /data-sketch="body-back"/);
        assert.match(html, /data-group="body"[^>]*aria-selected="true"/);
    }

    const head = await render(AnatomicalViews, {view: 'head-front'});
    assert.equal([...head.matchAll(/<img\b/g)].length, 5);
    assert.match(head, /data-sketch="head-front"/);
    assert.match(head, /data-sketch="head-top"/);
    assert.match(head, /data-group="head"[^>]*aria-selected="true"/);
    assert.doesNotMatch(head, /data-sketch="body-(?:front|back)"/);

    for (const [group, view] of [['hands', 'hand-right-dorsal'], ['feet', 'foot-left-plantar']]) {
        const html = await render(AnatomicalViews, {view});
        assert.equal([...html.matchAll(/<img\b/g)].length, 4);
        assert.match(html, new RegExp(`data-group="${group}"[^>]*aria-selected="true"`));
    }
});

test('tabs indicate mapped observations in other categories and respect pending writes', async () => {
    const html = await render(AnatomicalViews, {
        disabled: true,
        observations: [{number: 1, type: 'Scar', sketch_id: 'foot-right-dorsal', x: 0.5, y: 0.5}],
    });
    const feet = html.match(/<button\b[^>]*data-group="feet"[^>]*>[\s\S]*?<\/button>/)[0];
    assert.match(feet, /Contains mapped observations/);
    assert.match(feet, /disabled/);
    assert.equal([...html.matchAll(/role="tab"[^>]*disabled/g)].length, 4);
});

test('the workspace includes the diagrams and keeps its existing default slot', async () => {
    const html = await render(Workspace);
    assert.match(html, /data-sketch="body-front"/);
    assert.doesNotMatch(html, />Workspace area</);

    const custom = await render(Workspace, {}, {default: () => h('p', 'Custom workspace')});
    assert.match(custom, /Custom workspace/);
    assert.doesNotMatch(custom, /data-sketch=/);
});

test('transfer controls work locally and require explicit callbacks for persistent workspaces', async () => {
    const local = await render(Workspace, {examination: {reference: 'Imported reference'}});
    assert.match(local, /Import JSON/);
    assert.match(local, /Export JSON/);
    assert.match(local, /Export CSV/);
    assert.match(local, /aria-label="Export PNG for Body · Front/);
    assert.match(local, /Imported reference/);
    assert.match(local, /accept=".json,application\/json"/);

    const persistent = await render(Workspace, {persistentObservations: true, examination: {id: 1}});
    assert.doesNotMatch(persistent, /Import JSON|Export JSON|Export CSV/);
    const empty = await render(Workspace, {persistentObservations: true, onImportExamination: () => {}, onExportExamination: () => {}});
    assert.match(empty, /Import JSON/);
    assert.doesNotMatch(empty, /Export JSON/);
    const connected = await render(Workspace, {persistentObservations: true, examination: {id: 1}, onImportExamination: () => {}, onExportExamination: () => {}});
    assert.match(connected, /Import JSON/);
    assert.match(connected, /Export CSV/);
});

test('hosts can disable workspace features independently and override observation colors', async () => {
    const html = await render(Workspace, {
        features: {undo_redo: false, density: false, import: false},
        markerColors: {Scar: {fill: '#123456', text: '#abcdef'}},
        observations: [{number: 1, type: 'Scar', sketch_id: 'body-front', x: 0.5, y: 0.3}],
    });
    assert.doesNotMatch(html, /Import JSON|aria-label="Undo"|aria-label="Redo"|aria-label="Density"/);
    assert.match(html, /Export JSON/);
    assert.equal([...html.matchAll(/--he-observation-color:#123456;--he-observation-foreground:#abcdef/g)].length, 3);

    const exportDisabled = await render(Workspace, {features: {export: false}});
    assert.match(exportDisabled, /Import JSON/);
    assert.doesNotMatch(exportDisabled, /Export JSON/);
    assert.doesNotMatch(exportDisabled, /Export PNG/);
});

test('workspace defaults select the starting anatomy, display mode, and slider height', async () => {
    const html = await render(Workspace, {
        workspaceDefaults: {default_view: 'hands', default_mode: 'density', diagram_height: 640},
    });
    assert.match(html, /data-group="hands"[^>]*aria-selected="true"/);
    assert.match(html, /aria-label="Density"[^>]*aria-pressed="true"/);
    assert.match(html, /aria-valuetext="640 pixels"/);
    assert.match(html, /--he-diagram-height:640px/);

    const fallback = await render(Workspace, {
        workspaceDefaults: {default_view: 'unknown', default_mode: 'density', diagram_height: 'invalid'},
        features: {density: false},
    });
    assert.match(fallback, /data-group="body"[^>]*aria-selected="true"/);
    assert.match(fallback, /aria-valuetext="560 pixels"/);
    assert.doesNotMatch(fallback, /aria-label="Density"|Relative density/);

    for (const [requested, expected] of [[100, 280], [651, 660], [1000, 900]]) {
        const height = await render(Workspace, {workspaceDefaults: {diagram_height: requested}});
        assert.ok(height.includes(`aria-valuetext="${expected} pixels"`));
    }
});

test('persistent observations require a saved examination, while local mode remains usable', async () => {
    const blank = await render(Workspace, {persistentObservations: true});
    assert.match(blank, /aria-label="Add observation"[^>]*disabled/);
    assert.match(blank, /Create an examination before adding observations\./);

    const saved = await render(Workspace, {
        persistentObservations: true,
        examination: {id: 4, reference: 'EX-4'},
        observations: [{id: 10, number: 3, type: 'Scar', label: 'Saved scar'}],
    });
    assert.doesNotMatch(saved, /aria-label="Add observation"[^>]*disabled/);
    assert.match(saved, /Saved scar/);
    assert.match(saved, /Edit observation 3/);

    const local = await render(Workspace);
    assert.doesNotMatch(local, /aria-label="Add observation"[^>]*disabled/);
});

test('the workspace exposes initially disabled history controls and keyboard shortcuts', async () => {
    const html = await render(Workspace);
    assert.match(html, /aria-label="Observation history"/);
    assert.match(html, /aria-label="Undo"[^>]*aria-keyshortcuts="Control\+Z Meta\+Z"[^>]*disabled/);
    assert.match(html, /aria-label="Redo"[^>]*aria-keyshortcuts="[^"]*Control\+Y"[^>]*disabled/);
    const toolbar = html.match(/<div class="he-toolbar"[^>]*>[\s\S]*?<\/button>\s*<\/div>/)[0];
    assert.doesNotMatch(toolbar, /aria-label="Undo"/);
    assert.match(html, /class="he-view-actions"[^>]*>[\s\S]*?aria-label="Undo"/);
    const custom = await render(Workspace, {}, {default: () => h('p', 'Custom map')});
    assert.match(custom, /aria-label="Undo"/);
});

test('numbered markers render on their own sketch with numeric positions and selection', async () => {
    const html = await render(AnatomicalViews, {
        observations: [
            {number: 1, type: 'Scar', label: 'Front scar', sketch_id: 'body-front', x: '0.250000', y: '0.500000'},
            {number: 2, type: 'Tattoo', sketch_id: 'body-back', x: '0.750000', y: '0.125000'},
            {number: 3, type: 'Scar', sketch_id: 'head-front', x: 0.5, y: 0.5},
            {number: 4, type: 'Other', sketch_id: null, x: null, y: null},
            {number: 5, type: 'Other', sketch_id: 'body-front', x: NaN, y: 0.5},
        ],
        selectedNumber: 2,
    });
    const front = html.match(/<figure\b[^>]*data-sketch="body-front"[^>]*>[\s\S]*?<\/figure>/)[0];
    const back = html.match(/<figure\b[^>]*data-sketch="body-back"[^>]*>[\s\S]*?<\/figure>/)[0];
    assert.match(front, /data-observation="1"/);
    assert.match(front, /left:25%;top:50%/);
    assert.doesNotMatch(front, /data-observation="2"/);
    assert.match(back, /data-observation="2"/);
    assert.match(back, /aria-pressed="true"/);
    assert.match(back, /left:75%;top:12.5%/);
    assert.doesNotMatch(html, /data-observation="[345]"/);
});

test('a pending location renders a draft marker without inventing a saved observation number', async () => {
    const html = await render(AnatomicalViews, {
        draftLocation: {sketch_id: 'body-back', x: 0.4, y: 0.6},
        placementEnabled: true,
    });
    assert.match(html, /data-draft-marker/);
    assert.match(html, /left:40%;top:60%/);
    assert.match(html, /Arrow keys/);
    assert.doesNotMatch(html, /data-observation=/);
});

test('a saved marker being moved is drawn only at its unsaved location', async () => {
    const observations = [
        {number: 1, type: 'Scar', sketch_id: 'body-front', x: 0.25, y: 0.5},
        {number: 2, type: 'Tattoo', sketch_id: 'body-front', x: 0.6, y: 0.3},
    ];

    // Pin 1 is open and has been moved to the back view.
    const moving = await render(AnatomicalViews, {
        observations, editingNumber: 1, placementEnabled: true,
        draftLocation: {number: 1, type: 'Scar', sketch_id: 'body-back', x: 0.4, y: 0.6},
    });
    assert.doesNotMatch(moving, /data-observation="1"/);
    assert.match(moving, /data-observation="2"/);
    const back = moving.match(/<figure\b[^>]*data-sketch="body-back"[^>]*>[\s\S]*?<\/figure>/)[0];
    assert.match(back, /data-draft-marker/);
    assert.match(back, /left:40%;top:60%/);
    assert.match(moving, /move marker 1, or focus it and use the arrow keys/);

    // Pin 1 is open but not moved yet.
    const opened = await render(AnatomicalViews, {observations, editingNumber: 1, placementEnabled: true});
    assert.match(opened, /data-observation="1"[^>]*aria-label="[^"]*Arrow keys move it\."/);
    assert.doesNotMatch(opened, /data-observation="2"[^>]*aria-label="[^"]*Arrow keys/);
    assert.doesNotMatch(opened, /data-draft-marker/);
});

test('the workspace passes saved locations to the sketches and offers initial placement for unmapped observations', async () => {
    const html = await render(Workspace, {
        observations: [
            {number: 1, type: 'Scar', sketch_id: 'body-back', x: 0.25, y: 0.75},
            {number: 2, type: 'Tattoo', sketch_id: null, x: null, y: null},
        ],
    });
    assert.match(html, /data-observation="1"/);
    assert.match(html, /aria-label="Select observation 1"/);
    assert.match(html, /aria-label="Place observation 2"/);
    assert.doesNotMatch(html, /aria-label="Place observation 1"/);
});

test('every built-in observation type has its distinct color on both markers and list numbers', async () => {
    const colors = {
        Unspecified: '#8a8a8a', Scar: '#776c5b', Tattoo: '#375f94', Birthmark: '#a15d8f',
        Injury: '#a56532', 'Gunshot wound': '#70324e', 'Stab wound': '#397864',
        Bruise: '#7b6a9d', Abrasion: '#b39a55', Other: '#70783c',
    };
    const observations = Object.keys(colors).map((type, index) => ({
        number: index + 1, type, label: 'Finding', sketch_id: 'body-front', x: 0.5, y: 0.5,
    }));
    const html = await render(Workspace, {observations});

    for (const observation of observations) {
        const marker = html.match(new RegExp(`<button\\b[^>]*data-observation="${observation.number}"[^>]*>`))[0];
        assert.ok(marker.includes(`--he-observation-color:${colors[observation.type]}`), observation.type);
        assert.ok(marker.includes(`${observation.type}: Finding`), 'type remains available without relying on color');
        const item = html.match(new RegExp(`<li\\b[^>]*class="he-item[^>]*>[\\s\\S]*?Select observation ${observation.number}"`))[0];
        assert.ok(item.includes(`--he-observation-color:${colors[observation.type]}`), observation.type);
    }
});

test('the legend labels used types once across all views and includes the current preview', async () => {
    const html = await render(AnatomicalViews, {
        observations: [
            {number: 1, type: 'Scar', sketch_id: 'body-front', x: 0.3, y: 0.5},
            {number: 2, type: 'Scar', sketch_id: 'body-back', x: 0.5, y: 0.5},
            {number: 3, type: 'Tattoo', sketch_id: 'head-front', x: 0.5, y: 0.5},
            {number: 4, type: 'Other'},
        ],
        draftLocation: {type: 'Birthmark', sketch_id: 'body-back', x: 0.2, y: 0.4},
        placementEnabled: true,
    });
    const legend = html.match(/<div\b[^>]*aria-label="Observation type legend"[^>]*>[\s\S]*?<\/ul>\s*<\/div>/)[0];
    assert.equal([...legend.matchAll(/<li\b/g)].length, 4);
    for (const type of ['Scar', 'Tattoo', 'Other', 'Birthmark']) assert.ok(legend.includes(type));
    const preview = html.match(/<button\b[^>]*data-draft-marker[^>]*>/)[0];
    assert.ok(preview.includes('--he-observation-color:#a15d8f'));
    assert.ok(preview.includes('Birthmark'));
});

test('custom types have stable individual colors and missing types have a labelled fallback', async () => {
    const observations = [null, 'Custom A', 'Custom B', '__proto__'].map((type, index) => ({
        number: index + 1, type, sketch_id: 'body-front', x: 0.5, y: 0.5,
    }));
    const html = await render(AnatomicalViews, {observations});
    const reversed = await render(AnatomicalViews, {observations: [...observations].reverse()});
    const colorFor = (markup, number) => markup.match(new RegExp(`data-observation="${number}"[^>]*--he-observation-color:([^;]+);`))[1];
    assert.equal(colorFor(html, 1), '#8a8a8a');
    assert.notEqual(colorFor(html, 2), colorFor(html, 3));
    for (const {number} of observations) assert.equal(colorFor(html, number), colorFor(reversed, number));
    assert.match(html, /Observation 1, Unspecified/);
    const empty = await render(AnatomicalViews);
    assert.match(empty, /aria-label="Observation type legend"/);
    assert.match(empty, /Your observations will appear here\./);
});
