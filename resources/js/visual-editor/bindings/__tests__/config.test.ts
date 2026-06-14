import { afterEach, beforeEach, describe, expect, it } from 'vitest'

import {
	getBindingsApiConfig,
	resetBindingsApiConfig,
	setBindingsApiConfig,
} from '../config'

beforeEach( () => {
	resetBindingsApiConfig()
} )

afterEach( () => {
	resetBindingsApiConfig()
} )

describe( 'bindings config', () => {
	it( 'defaults to the /visual-editor/api base', () => {
		expect( getBindingsApiConfig().apiBase ).toBe( '/visual-editor/api' )
	} )

	it( 'lets callers override the apiBase', () => {
		setBindingsApiConfig( { apiBase: 'https://example.test/ve/api' } )

		expect( getBindingsApiConfig().apiBase ).toBe( 'https://example.test/ve/api' )
	} )

	it( 'strips trailing slashes from the supplied apiBase', () => {
		setBindingsApiConfig( { apiBase: 'https://example.test/ve/api/' } )

		expect( getBindingsApiConfig().apiBase ).toBe( 'https://example.test/ve/api' )
	} )

	it( 'reset returns to the default base', () => {
		setBindingsApiConfig( { apiBase: 'https://x.test/api' } )
		resetBindingsApiConfig()

		expect( getBindingsApiConfig().apiBase ).toBe( '/visual-editor/api' )
	} )
} )
