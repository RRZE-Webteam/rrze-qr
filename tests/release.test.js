const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

function fixture() {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'rrze-qr-release-'));
    fs.copyFileSync(path.join(__dirname, '../increment-version.js'), path.join(directory, 'increment-version.js'));
    fs.writeFileSync(path.join(directory, 'package.json'), JSON.stringify({ version: '1.1.0' }));
    fs.writeFileSync(path.join(directory, 'package-lock.json'), JSON.stringify({ version: '1.1.0', packages: { '': { version: '1.1.0' } } }));
    fs.writeFileSync(path.join(directory, 'rrze-qr.php'), '<?php\n/*\nVersion: 1.1.0\n*/\n');
    return directory;
}

test('release commands synchronize every version, including the lockfile', () => {
    for (const [type, expected] of [['major', '2.0.0'], ['minor', '1.2.0'], ['patch', '1.1.1']]) {
        const directory = fixture();
        try {
            fs.writeFileSync(path.join(directory, 'readme.txt'), 'Stable tag: 1.1.0\n');
            const result = spawnSync(process.execPath, ['increment-version.js', type], { cwd: directory });
            assert.equal(result.status, 0, result.stderr.toString());
            const read = name => fs.readFileSync(path.join(directory, name), 'utf8');
            assert.equal(JSON.parse(read('package.json')).version, expected);
            assert.equal(JSON.parse(read('package-lock.json')).version, expected);
            assert.equal(JSON.parse(read('package-lock.json')).packages[''].version, expected);
            assert.ok(read('rrze-qr.php').includes('Version: ' + expected));
            assert.ok(read('readme.txt').includes('Stable tag: ' + expected));
        } finally { fs.rmSync(directory, { recursive: true, force: true }); }
    }
});

test('inconsistent release metadata fails without partially updating files', () => {
    const directory = fixture();
    try {
        const pluginPath = path.join(directory, 'rrze-qr.php');
        fs.writeFileSync(pluginPath, '<?php\n/* Version: 0.0.0 */\n');
        const before = fs.readdirSync(directory).map(name => [name, fs.readFileSync(path.join(directory, name), 'utf8')]);
        const result = spawnSync(process.execPath, ['increment-version.js', 'major'], { cwd: directory });
        assert.equal(result.status, 1);
        for (const [name, content] of before) {
            assert.equal(fs.readFileSync(path.join(directory, name), 'utf8'), content);
        }
    } finally { fs.rmSync(directory, { recursive: true, force: true }); }
});
