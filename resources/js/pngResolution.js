// Embeds print density in a PNG; canvas PNGs carry none, so programs guess the print size.
export async function pngPrintResolution(blob, dpi) {
    const bytes = new Uint8Array(await blob.arrayBuffer());
    const input = new DataView(bytes.buffer);
    const chunk = new Uint8Array(21);
    const view = new DataView(chunk.buffer);
    const pixelsPerMeter = Math.round(dpi / 0.0254);
    view.setUint32(0, 9);
    chunk.set(new TextEncoder().encode('pHYs'), 4);
    view.setUint32(8, pixelsPerMeter);
    view.setUint32(12, pixelsPerMeter);
    chunk[16] = 1;

    let crc = 0xffffffff;
    for (const byte of chunk.subarray(4, 17)) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
    view.setUint32(17, (crc ^ 0xffffffff) >>> 0);

    // The PNG signature and IHDR chunk always take the first 33 bytes.
    const parts = [bytes.slice(0, 33), chunk];
    for (let offset = 33; offset < bytes.length;) {
        const length = input.getUint32(offset) + 12;
        if (input.getUint32(offset + 4) !== 0x70485973) parts.push(bytes.slice(offset, offset + length));
        offset += length;
    }
    return new Blob(parts, {type: 'image/png'});
}
