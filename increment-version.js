const fs = require('node:fs');
const path = require('node:path');

const incrementType = process.argv[2];
if (!['major', 'minor', 'patch'].includes(incrementType)) {
    console.error('Usage: node increment-version.js [major|minor|patch]');
    process.exit(1);
}

// Prepare and validate every change before writing any release metadata.
try {
    const packagePath = path.join(__dirname, 'package.json');
    const lockPath = path.join(__dirname, 'package-lock.json');
    const pluginPath = path.join(__dirname, 'rrze-qr.php');
    const readmePath = path.join(__dirname, 'readme.txt');
    const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    const lock = JSON.parse(fs.readFileSync(lockPath, 'utf8'));
    const oldVersion = pkg.version;
    if (!/^\d+\.\d+\.\d+$/.test(oldVersion)) {
        throw new Error('Expected a numeric major.minor.patch version in package.json.');
    }
    if (lock.version !== oldVersion || lock.packages?.['']?.version !== oldVersion) {
        throw new Error('package.json and package-lock.json versions must match before releasing.');
    }
    const [major, minor, patch] = oldVersion.split('.').map(Number);
    const newVersion = incrementType === 'major' ? `${major + 1}.0.0`
        : incrementType === 'minor' ? `${major}.${minor + 1}.0` : `${major}.${minor}.${patch + 1}`;
    function updateTag(file, label) {
        const content = fs.readFileSync(file, 'utf8');
        const expression = new RegExp(`(^[\\t ]*${label}[\\t ]*:[\\t ]*)([\\d.]+)`, 'm');
        const match = content.match(expression);
        if (!match || match[2] !== oldVersion) {
            throw new Error(`${label} in ${path.basename(file)} must match ${oldVersion}.`);
        }
        return content.replace(expression, (_, prefix) => prefix + newVersion);
    }
    const changes = [[pluginPath, updateTag(pluginPath, 'Version')]];
    if (fs.existsSync(readmePath)) { changes.push([readmePath, updateTag(readmePath, 'Stable tag')]); }
    pkg.version = newVersion;
    lock.version = newVersion;
    lock.packages[''].version = newVersion;
    changes.push([packagePath, JSON.stringify(pkg, null, 2) + '\n']);
    changes.push([lockPath, JSON.stringify(lock, null, 2) + '\n']);
    for (const [file, content] of changes) { fs.writeFileSync(file, content); }
    console.log(`Release version updated from ${oldVersion} to ${newVersion}.`);
} catch (error) {
    console.error(error.message);
    process.exit(1);
}
