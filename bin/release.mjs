#!/usr/bin/env node
//* Release helper, called by the npm version lifecycle (see package.json) and by the release workflow.
//*   check          → fail if CHANGELOG.md has no entry under [Unreleased]                 (preversion)
//*   sync           → copy the package.json version to the plugin header, the
//*                    SIMPLE_POPUP_VERSION constant and readme.txt,
//*                    turn [Unreleased] into [x.y.z] - date, and regenerate the
//*                    readme.txt "== Changelog ==" section from CHANGELOG.md        (version)
//*   notes <x.y.z>  → print the CHANGELOG.md section of a version                          (workflow)
import { readFileSync, writeFileSync } from 'node:fs'

const [ mode, arg ] = process.argv.slice( 2 )
const CHANGELOG = 'CHANGELOG.md'

const fail = ( message ) => {
	console.error( `release: ${ message }` )
	process.exit( 1 )
}

//* Body of a "## [name]" section, up to the next "## [" heading or the end of the file
const section = ( text, name ) => {
	const start = text.indexOf( `## [${ name }]` )
	if ( start === -1 ) return null
	const bodyStart = text.indexOf( '\n', start ) + 1
	const next = text.indexOf( '\n## [', bodyStart )
	return text.slice( bodyStart, next === -1 ? undefined : next ).trim()
}

const replaceOrFail = ( file, pattern, replacer ) => {
	const content = readFileSync( file, 'utf8' )
	if ( ! pattern.test( content ) ) fail( `${ file }: ${ pattern } not found` )
	writeFileSync( file, content.replace( pattern, replacer ) )
}

//* CHANGELOG.md versions in readme.txt format: "= x.y.z =" then "* Kind: entry" lines
const readmeChangelog = ( text ) => {
	const lines = []
	let inVersion = false
	let kind = ''
	for ( const line of text.split( '\n' ) ) {
		const version = line.match( /^## \[(\d+\.\d+\.\d+)\]/ )
		if ( version ) {
			lines.push( '', `= ${ version[ 1 ] } =` )
			inVersion = true
		} else if ( line.startsWith( '## ' ) ) {
			inVersion = false
		} else if ( ! inVersion ) {
			continue
		} else if ( line.startsWith( '### ' ) ) {
			kind = line.slice( 4 ).trim()
		} else if ( line.startsWith( '- ' ) ) {
			lines.push( `* ${ kind }: ${ line.slice( 2 ).trim() }` )
		} else if ( line.trim() ) {
			lines[ lines.length - 1 ] += ' ' + line.trim()
		}
	}
	return lines.join( '\n' ).trim()
}

const changelog = readFileSync( CHANGELOG, 'utf8' )

if ( mode === 'check' ) {
	if ( ! /^- /m.test( section( changelog, 'Unreleased' ) ?? '' ) ) {
		fail( `${ CHANGELOG }: add at least one entry under "## [Unreleased]" before releasing` )
	}
} else if ( mode === 'sync' ) {
	const { version } = JSON.parse( readFileSync( 'package.json', 'utf8' ) )
	replaceOrFail( 'simple-popup.php', /^( \* Version:\s+).+$/m, ( _, prefix ) => prefix + version )
	replaceOrFail( 'simple-popup.php', /(define\( 'SIMPLE_POPUP_VERSION', ')[^']+(' \);)/, ( _, prefix, suffix ) => prefix + version + suffix )
	replaceOrFail( 'readme.txt', /^(Stable tag:\s*).+$/m, ( _, prefix ) => prefix + version )
	const today = new Date().toLocaleDateString( 'en-CA' )
	const updated = changelog.replace( '## [Unreleased]', `## [Unreleased]\n\n## [${ version }] - ${ today }` )
	writeFileSync( CHANGELOG, updated )
	replaceOrFail( 'readme.txt', /(^== Changelog ==\n)[\s\S]*$/m, ( _, heading ) => `${ heading }\n${ readmeChangelog( updated ) }\n` )
} else if ( mode === 'notes' ) {
	const notes = section( changelog, arg )
	if ( ! notes ) fail( `${ CHANGELOG }: no section for ${ arg }` )
	console.log( notes )
} else {
	fail( 'usage: release.mjs check | sync | notes <version>' )
}
