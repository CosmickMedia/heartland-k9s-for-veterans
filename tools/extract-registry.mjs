#!/usr/bin/env node
/**
 * extract-registry.mjs — build BarKode registry records (hk9_barkode) from the live
 * root-level record pages (Avada two-column layout: K9 photo + a fusion-text of <p> lines).
 *
 * Usage:
 *   node tools/extract-registry.mjs                 # writes payload-src/records/barkode/<slug>.json (gitignored)
 *   node tools/extract-registry.mjs --pages file    # alternative live pages JSON (REST shape)
 *   node tools/extract-registry.mjs --summary       # print counts only (never prints field values)
 *
 * Field mapping follows docs/ARCHITECTURE.md §6 (hk9_barkode). Sensitive handling:
 *   - handler phone numbers and any residential street address are NOT stored in rendered fields;
 *     they go to `_hk9_review_notes` ("pending client review, not rendered").
 *   - every record carries `sensitive: true`; the master template is imported as a draft example.
 * The report printed by this tool contains counts only. Record files must never be committed.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as cheerio from 'cheerio';
import { ROOT, loadContext, findMedia, cleanTitle } from './fusion-to-blocks.mjs';

const OUT_DIR = path.join( ROOT, 'payload-src/records/barkode' );
const SOURCE_DIR = path.join( ROOT, 'payload-src/source' );
const MASTER_TEMPLATE_ID = 3673;

/** Live page ids that are registry records (classification "barkode-record" in discovery/live/inventory.json) + the master template. */
function registryPageIds( ctx ) {
	const ids = ctx.inventory.pages.filter( ( p ) => p.classification === 'barkode-record' ).map( ( p ) => p.id );
	if ( ! ids.includes( MASTER_TEMPLATE_ID ) ) {
		ids.push( MASTER_TEMPLATE_ID );
	}
	return ids;
}

const PHONE_RE = /\(?\b\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/g;
const ADDRESS_RE = /\b\d{2,6}\s+(?:[NSEW]\.?\s+)?(?:County\s+Road|CR|Rd|Road|St|Street|Ave|Avenue|Dr|Drive|Ln|Lane|Blvd|Boulevard|Hwy|Highway|Ct|Court|Cir|Circle|Way|Pl|Place|Trl|Trail)\b/i;
const ZIP_RE = /\b[A-Z]{2}\s+\d{5}(?:-\d{4})?\b/;
const ID_RE = /((?:hk9|k9)t?\s?\d{2,4}-?\d{2,3})/i;

const clean = ( s ) => String( s || '' ).replace( / /g, ' ' ).replace( /\s+/g, ' ' ).trim();
const stripLabel = ( line, re ) => clean( line.replace( re, '' ) ).replace( /^[-–:—\s]+/, '' ).trim();

/** Normalise a registry id token found in a slug / title / photo title. */
function normalizeId( raw ) {
	if ( ! raw ) {
		return '';
	}
	let id = clean( raw ).toUpperCase();
	const m = id.match( /^HK9(\d{2})(\d{3})$/ ); // hk923004 -> HK923-004 (matches the HK923-005 sibling)
	if ( m ) {
		id = `HK9${ m[ 1 ] }-${ m[ 2 ] }`;
	}
	return id;
}

function programType( label ) {
	const l = label.toLowerCase();
	if ( l.includes( 'training' ) ) {
		return 'in-training';
	}
	if ( l.includes( 'therapy' ) ) {
		return 'therapy';
	}
	return 'service';
}

/**
 * Parse the fusion-text lines of one record page.
 * Returns { fields, review, unclassified } — never logs values.
 */
function parseLines( lines, pageTitle, slug, photoTitle ) {
	const f = {
		dog_name: '', program_label: '', registry_id: '', breed: '', task_description: '', tasks: '',
		handler_name: '', emergency_contact: '', vet_contact: '', certification: '', do_not_separate: false,
		notice: '', contact_line: '',
	};
	const review = [];
	const unclassified = [];

	// --- dog name + program label from line 1 or the page title -----------------
	const titleM = pageTitle.match( /^(.+?)\s*\(([^)]+)\)/ );
	const first = lines[ 0 ] || '';
	const firstM = first.match( /^(.+?)\s*\(([^)]+)\)\s*$/ );
	let consumed = 0;
	if ( firstM ) {
		f.dog_name = clean( firstM[ 1 ] );
		f.program_label = clean( firstM[ 2 ] );
		consumed = 1;
	} else if ( first && ! /\b(breed|dog|k9|retriever|lab|poodle|shep|pinscher|schnauzer|pointer|therapy)\b/i.test( first ) && first.split( ' ' ).length <= 2 ) {
		// bare dog name line (e.g. "Tex")
		f.dog_name = clean( first );
		consumed = 1;
	}
	if ( ! f.dog_name && titleM ) {
		// "Larry and Archie (Service K9)" -> the K9 is the last name
		const names = clean( titleM[ 1 ] ).split( /\s+and\s+/i );
		f.dog_name = names.at( -1 );
	}
	if ( ! f.program_label && titleM ) {
		f.program_label = clean( titleM[ 2 ] );
	}
	if ( /^service dog$/i.test( f.program_label ) ) {
		f.program_label = 'Service K9';
	}

	// --- registry id --------------------------------------------------------------
	const idLine = lines.find( ( l ) => /service number identification/i.test( l ) );
	if ( idLine ) {
		f.registry_id = normalizeId( stripLabel( idLine, /service number identification/i ) );
	}
	for ( const src of [ slug, pageTitle, photoTitle ] ) {
		if ( f.registry_id ) {
			break;
		}
		const m = String( src || '' ).match( ID_RE );
		if ( m ) {
			f.registry_id = normalizeId( m[ 1 ] );
			if ( src === slug && /^hk9\d{5}$/i.test( m[ 1 ] ) ) {
				review.push( `Registry ID "${ f.registry_id }" was inferred from the legacy slug "${ slug }" (no hyphen on the live site).` );
			}
			if ( src === photoTitle ) {
				review.push( 'Registry ID taken from the K9 photo title (not printed in the page text).' );
			}
		}
	}
	if ( f.registry_id && /^K9T/.test( f.registry_id ) ) {
		review.push( `Registry ID "${ f.registry_id }" is copied from the legacy slug; other therapy records use the HK9T prefix — confirm.` );
	}

	// --- classify remaining lines ---------------------------------------------------
	let section = 'breed';
	const breedLines = [];
	const taskLines = [];
	const emergencyLines = [];
	const vetLines = [];
	for ( let i = consumed; i < lines.length; i++ ) {
		const line = clean( lines[ i ] );
		if ( ! line ) {
			continue;
		}
		if ( /service number identification/i.test( line ) ) {
			continue;
		}
		if ( /^any questions/i.test( line ) ) {
			f.contact_line = line;
			section = 'done';
			continue;
		}
		if ( /do not sep[ae]rate/i.test( line ) ) {
			f.do_not_separate = true;
			section = 'after-cert';
			continue;
		}
		if ( /^hk9\s*certified/i.test( line ) ) {
			f.certification = line;
			if ( /hk9\(\s/i.test( line ) ) {
				// live typo "Hk9( Good Conduct Certified"
				f.certification = line.replace( /hk9\(\s*/i, 'HK9 ' );
				review.push( 'Certification line: removed a stray "(" present on the live page.' );
			}
			section = 'after-cert';
			continue;
		}
		if ( /^(emergency\s+)?vet(erinary)?\s*contact/i.test( line ) ) {
			vetLines.push( stripLabel( line, /^(emergency\s+)?vet(erinary)?\s*contact/i ) );
			section = 'vet';
			continue;
		}
		if ( /^emergency\s*contact/i.test( line ) ) {
			emergencyLines.push( stripLabel( line, /^emergency\s*contact/i ) );
			section = 'emergency';
			continue;
		}
		if ( /^handler/i.test( line ) ) {
			let h = stripLabel( line, /^handler/i );
			const phones = h.match( PHONE_RE );
			if ( phones ) {
				review.push( `Handler phone number(s) removed from the Handler line: ${ phones.join( ', ' ) } (pending client review, not rendered).` );
				h = clean( h.replace( PHONE_RE, '' ) ).replace( /[-–,]\s*$/, '' ).trim();
			}
			f.handler_name = h;
			section = 'handler';
			continue;
		}
		if ( /^responsibilities\s*\/\s*tasks/i.test( line ) ) {
			const rest = stripLabel( line, /^responsibilities\s*\/\s*tasks/i );
			if ( rest ) {
				taskLines.push( rest );
			}
			section = 'tasks';
			continue;
		}
		if ( section === 'vet' ) {
			// a veterinary clinic's business address is part of the vet contact (rendered as on the live record)
			vetLines.push( line );
			continue;
		}
		if ( ADDRESS_RE.test( line ) || ZIP_RE.test( line ) ) {
			review.push( `Residential/street address line withheld from the record: "${ line }" (pending client review, not rendered).` );
			continue;
		}
		if ( section === 'breed' ) {
			if ( ! f.task_description && /\b(service (dog|k9)|therapy (dog|certified)|task description)\b/i.test( line ) ) {
				f.task_description = line;
				section = 'after-task';
			} else {
				breedLines.push( line );
			}
			continue;
		}
		if ( section === 'after-task' ) {
			// a task description may spill onto a second line before "Responsibilities/Tasks"
			if ( ! /^responsibilities/i.test( line ) ) {
				f.task_description = clean( `${ f.task_description } ${ line }` );
			}
			continue;
		}
		if ( section === 'tasks' ) {
			taskLines.push( line );
			continue;
		}
		if ( section === 'emergency' ) {
			emergencyLines.push( line );
			continue;
		}
		if ( section === 'after-cert' || section === 'handler' ) {
			// therapy-dog traveller sentence or any other note
			if ( /traveler|traveller|knows (his|her) way home/i.test( line ) ) {
				f.notice = line;
				continue;
			}
		}
		unclassified.push( line );
	}

	f.breed = breedLines.join( ' ' );
	f.tasks = taskLines.join( '\n' );
	f.emergency_contact = emergencyLines.filter( Boolean ).join( '\n' );
	f.vet_contact = vetLines.filter( Boolean ).join( '\n' );
	for ( const line of unclassified ) {
		review.push( `Unclassified line kept for review: "${ line }" (pending client review, not rendered).` );
	}
	return { fields: f, review, unclassified };
}

function extractRecord( page, ctx ) {
	const $ = cheerio.load( String( page.content?.rendered || '' ).replace( /<!--[\s\S]*?-->/g, '' ), null, false );
	const title = cleanTitle( page.title?.rendered || '' );
	const slug = page.slug;
	const isMaster = page.id === MASTER_TEMPLATE_ID;

	// images: first = K9 photo; one titled "ID Back" = ID card image
	const images = [];
	$( 'img' ).each( ( _, img ) => {
		const $img = $( img );
		const hint = parseInt( ( ( $img.attr( 'class' ) || '' ).match( /wp-image-(\d+)/ ) || [] )[ 1 ] || '0', 10 );
		const found = findMedia( ctx, $img.attr( 'src' ) || '', hint );
		images.push( { id: found ? found.item.id : 0, title: clean( $img.attr( 'title' ) || ( found ? found.item.title : '' ) ), src: $img.attr( 'src' ) } );
	} );
	const idCards = images.filter( ( im ) => /id[\s-]*back|id[\s-]*card|id[\s-]*front/i.test( im.title ) );
	const photo = images.find( ( im ) => ! idCards.includes( im ) ) || null;

	const lines = [];
	$( '.fusion-text p, .fusion-text div, .fusion-text li' ).each( ( _, p ) => {
		if ( $( p ).children( 'p, div, li' ).length ) {
			return; // nested wrapper (Facebook-pasted <div> trees): only leaf lines count
		}
		const t = clean( $( p ).text() );
		if ( t ) {
			lines.push( t );
		}
	} );

	const { fields: f, review } = parseLines( lines, title, slug, photo ? photo.title : '' );
	const unmatched = images.filter( ( im ) => ! im.id );
	for ( const im of unmatched ) {
		review.push( `Image could not be matched to the media manifest: ${ im.src }` );
	}
	if ( ! lines.length ) {
		review.push( 'Live page carried no text — the record is image-only (photo + ID card); fields left empty pending client review.' );
	}

	// title: "<Dog> (<Program type>) <ID>" — handler names live in the field, never in the title
	let recTitle = isMaster ? 'BarKode Master Template (example)' : `${ f.dog_name } (${ f.program_label })${ f.registry_id ? ` ${ f.registry_id }` : '' }`.trim();
	if ( isMaster ) {
		f.dog_name = 'Dog Name';
	}

	const str = ( v ) => ( { type: 'string', value: v || '' } );
	const rec = {
		key: `live:barkode:${ page.id }`,
		type: 'hk9_barkode',
		status: isMaster ? 'draft' : 'publish',
		slug,
		title: recTitle,
		date: `${ page.date_gmt }Z`,
		sensitive: true,
		meta: {
			hk9_dog_name: str( f.dog_name ),
			hk9_program_type: str( programType( f.program_label ) ),
			hk9_registry_id: str( f.registry_id ),
			hk9_legacy_path: str( `/${ slug }/` ),
			hk9_breed: str( f.breed ),
			hk9_task_description: str( f.task_description ),
			hk9_tasks: str( f.tasks ),
			hk9_handler_name: str( f.handler_name ),
			hk9_emergency_contact: str( f.emergency_contact ),
			hk9_vet_contact: str( f.vet_contact ),
			hk9_certification: str( f.certification ),
			hk9_do_not_separate: { type: 'boolean', value: !! f.do_not_separate },
			hk9_notice: str( f.notice ),
			hk9_contact_line: str( f.contact_line ),
			hk9_id_card_images: { type: 'array', value: idCards.filter( ( im ) => im.id ).map( ( im ) => `{{media:live:media:${ im.id }}}` ) },
			hk9_status_note: str( isMaster ? 'Master template imported as a draft example — not a real team.' : '' ),
			_hk9_review_notes: str( review.length ? review.map( ( r ) => `- ${ r }` ).join( '\n' ) : '' ),
		},
	};
	if ( photo && photo.id ) {
		rec.featured = `{{media:live:media:${ photo.id }}}`;
	}
	return { rec, stats: { lines: lines.length, review: review.length, images: images.length, idCards: idCards.filter( ( im ) => im.id ).length, hasPhoto: !! ( photo && photo.id ), registryId: !! f.registry_id, handler: !! f.handler_name, emergency: !! f.emergency_contact, vet: !! f.vet_contact } };
}

function parseArgs( argv ) {
	const a = { pages: '', summary: false };
	for ( let i = 0; i < argv.length; i++ ) {
		if ( argv[ i ] === '--pages' ) a.pages = argv[ ++i ];
		else if ( argv[ i ] === '--summary' ) a.summary = true;
	}
	return a;
}

function main() {
	const args = parseArgs( process.argv.slice( 2 ) );
	const ctx = loadContext();
	const pagesPath = args.pages || path.join( SOURCE_DIR, 'live-pages.json' );
	if ( ! fs.existsSync( pagesPath ) ) {
		throw new Error( `live pages JSON not found at ${ pagesPath } — run tools/fusion-to-blocks.mjs --fetch first` );
	}
	const pages = JSON.parse( fs.readFileSync( pagesPath, 'utf8' ) );
	const ids = registryPageIds( ctx );
	fs.mkdirSync( OUT_DIR, { recursive: true } );
	fs.mkdirSync( SOURCE_DIR, { recursive: true } );

	const totals = { records: 0, published: 0, draft: 0, withPhoto: 0, withIdCard: 0, withRegistryId: 0, withHandler: 0, withEmergency: 0, withVet: 0, withReviewNotes: 0, reviewLines: 0 };
	for ( const id of ids ) {
		const page = pages.find( ( p ) => p.id === id );
		if ( ! page ) {
			console.error( `page ${ id } not in input` );
			continue;
		}
		const { rec, stats } = extractRecord( page, ctx );
		if ( ! args.summary ) {
			fs.writeFileSync( path.join( OUT_DIR, `${ rec.slug }.json` ), JSON.stringify( rec, null, 2 ) + '\n' );
			fs.writeFileSync( path.join( SOURCE_DIR, `live__page__${ page.id }.html` ), page.content?.rendered || '' );
		}
		totals.records++;
		totals[ rec.status === 'draft' ? 'draft' : 'published' ]++;
		if ( stats.hasPhoto ) totals.withPhoto++;
		if ( stats.idCards ) totals.withIdCard++;
		if ( stats.registryId ) totals.withRegistryId++;
		if ( stats.handler ) totals.withHandler++;
		if ( stats.emergency ) totals.withEmergency++;
		if ( stats.vet ) totals.withVet++;
		if ( stats.review ) totals.withReviewNotes++;
		totals.reviewLines += stats.review;
	}
	console.log( JSON.stringify( totals ) );
	if ( ! args.summary ) {
		console.log( `wrote ${ totals.records } records → ${ path.relative( ROOT, OUT_DIR ) } (gitignored; counts only are reported)` );
	}
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	main();
}
