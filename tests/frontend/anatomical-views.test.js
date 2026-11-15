import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createServer } from 'vite';
import { sketches } from '../../resources/js/sketches.js';

let server;
let AnatomicalViews;
let Workspace;

before(async () => {
    server = await createServer({
        configFile: false,
        plugins: [vue()],
        optimizeDeps: { noDiscovery: true },
        server: { middlewareMode: true, hmr: false },
    });
    AnatomicalViews = (await server.ssrLoadModule('/resources/js/components/AnatomicalViews.vue')).default;
    Workspace = (await server.ssrLoadModule('/resources/js/components/HumanExaminationWorkspace.vue')).default;
});

after(async () => {
    await server?.close();
});

function render(component, props = {}, slots = {}) {
    return renderToString(createSSRApp({ render: () => h(component, props, slots) }));
}

test('the default view offers a labelled selector and both body diagrams', async () => {
    const html = await render(AnatomicalViews);

    assert.match(html, /<label\b[^>]*>\s*<span[^>]*>Anatomical view<\/span>\s*<select\b/);
    assert.equal([...html.matchAll(/<optgroup\b/g)].length, 4);
    assert.equal([...html.matchAll(/<option\b/g)].length, 14);
    assert.match(html, /Body · Front and back/);
    assert.match(html, /data-sketch="body-front"/);
    assert.match(html, /data-sketch="body-back"/);
    assert.equal([...html.matchAll(/<img\b/g)].length, 2);
    assert.match(html, /<img\b[^>]*alt="[^"]+anatomical sketch"/);
    assert.match(html, /Anatomical left and right refer to the subject\./);
});

test('each stable view ID selects the corresponding diagram and aspect ratio', async () => {
    for (const sketch of sketches) {
        const html = await render(AnatomicalViews, { view: sketch.id });

        assert.ok(html.includes(`data-sketch="${sketch.id}"`), sketch.id);
        assert.ok(html.includes(`src="${sketch.src}"`), sketch.id);
        assert.ok(html.includes(`--he-sketch-ratio:${sketch.width / sketch.height}`), sketch.id);
    }
});

test('an unknown view ID falls back to the paired body diagrams', async () => {
    const html = await render(AnatomicalViews, { view: 'not-a-sketch' });

    assert.match(html, /data-sketch="body-front"/);
    assert.match(html, /data-sketch="body-back"/);
    assert.ok(html.includes(`src="${sketches[0].src}"`));
});

test('both body IDs display the pair while other anatomical views display individually', async () => {
    for (const view of ['body-front', 'body-back']) {
        const html = await render(AnatomicalViews, { view });
        assert.equal([...html.matchAll(/<img\b/g)].length, 2);
        assert.match(html, /data-sketch="body-front"/);
        assert.match(html, /data-sketch="body-back"/);
        assert.match(html, /<option\b[^>]*value="body-front"[^>]*selected/);
    }

    const head = await render(AnatomicalViews, { view: 'head-front' });
    assert.equal([...head.matchAll(/<img\b/g)].length, 1);
    assert.match(head, /data-sketch="head-front"/);
    assert.doesNotMatch(head, /data-sketch="body-(?:front|back)"/);
});

test('the workspace includes the diagrams and keeps its existing default slot', async () => {
    const html = await render(Workspace);
    assert.match(html, /data-sketch="body-front"/);
    assert.doesNotMatch(html, />Workspace area</);

    const custom = await render(Workspace, {}, { default: () => h('p', 'Custom workspace') });
    assert.match(custom, /Custom workspace/);
    assert.doesNotMatch(custom, /data-sketch=/);
});

test('persistent observations require a saved examination, while local mode remains usable', async () => {
    const blank = await render(Workspace, { persistentObservations: true });
    assert.match(blank, /aria-label="Add observation"[^>]*disabled/);
    assert.match(blank, /Create an examination before adding observations\./);

    const saved = await render(Workspace, {
        persistentObservations: true,
        examination: { id: 4, reference: 'EX-4' },
        observations: [{ id: 10, number: 3, type: 'Scar', label: 'Saved scar' }],
    });
    assert.doesNotMatch(saved, /aria-label="Add observation"[^>]*disabled/);
    assert.match(saved, /Saved scar/);
    assert.match(saved, /Edit observation 3/);

    const local = await render(Workspace);
    assert.doesNotMatch(local, /aria-label="Add observation"[^>]*disabled/);
});
