/**
 * scripts/vite-dev.mjs
 *
 * Startet den Vite Dev Server und verwaltet die "hot"-Datei,
 * die WordPress signalisiert dass der Dev Server läuft.
 *
 * Verwendung: npm run dev
 */

import { createServer } from 'vite';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const HOT_FILE  = path.resolve( __dirname, '../cms/wp-content/themes/stadtwirt-theme/assets/hot' );

// Hot-Datei erstellen → WordPress wechselt in den Dev-Modus
function createHotFile() {
    try {
        fs.writeFileSync( HOT_FILE, 'http://localhost:3000' );
        console.log( '✓ Vite Dev Mode aktiv — hot-Datei erstellt' );
    } catch ( e ) {
        console.error( '✗ Hot-Datei konnte nicht erstellt werden:', e.message );
    }
}

// Hot-Datei löschen → WordPress wechselt zurück in den Prod-Modus
function removeHotFile() {
    try {
        if ( fs.existsSync( HOT_FILE ) ) {
            fs.unlinkSync( HOT_FILE );
            console.log( '\n✓ Hot-Datei entfernt — WordPress zurück im Prod-Modus' );
        }
    } catch ( e ) {
        console.error( '✗ Hot-Datei konnte nicht entfernt werden:', e.message );
    }
}

// Aufräumen bei Beenden (Ctrl+C, kill, etc.)
process.on( 'SIGINT',  () => { removeHotFile(); process.exit(0); } );
process.on( 'SIGTERM', () => { removeHotFile(); process.exit(0); } );
process.on( 'exit',    () => removeHotFile() );

// Vite Dev Server starten
async function start() {
    const server = await createServer();
    await server.listen();

    createHotFile();
    server.printUrls();

    console.log( '\n  Beobachte PHP-Änderungen für Live Reload...' );
    console.log( '  Stoppen mit Ctrl+C\n' );
}

start().catch( ( err ) => {
    console.error( err );
    removeHotFile();
    process.exit(1);
} );
