const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = execFileSync('git', ['rev-parse', '--show-toplevel'], {
    encoding: 'utf8',
}).trim();
const files = execFileSync(
    'git',
    ['-C', root, 'ls-files', '--full-name', '-z'],
    {
        encoding: 'utf8',
    },
)
    .split('\0')
    .filter(Boolean);
const findings = [];
for (const file of files) {
    if (
        /(^|\/)(\.runtime|copias|node_modules|vendor|secrets)(\/|$)|(^|\/)\.env(?:\.[^/]+)?$|\.(?:ssbk|dump|pfx|p12|pem|key)$/.test(
            file,
        ) &&
        !file.endsWith('/.env.example')
    ) {
        findings.push(file + ': archivo privado o generado');
        continue;
    }
    const bytes = fs.readFileSync(path.join(root, file));
    if (bytes.includes(0)) continue;
    if (
        /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bgh[pousr]_[A-Za-z0-9]{30,}\b|\bgithub_pat_[A-Za-z0-9_]{40,}\b/.test(
            bytes.toString('utf8'),
        )
    )
        findings.push(file + ': posible credencial');
}
if (findings.length) {
    for (const finding of findings) process.stderr.write(finding + '\n');
    process.exit(1);
}
process.stdout.write(
    `${files.length} archivos revisados sin patrones de claves ni rutas privadas. Revisión acotada, no garantía de ausencia de secretos.\n`,
);
