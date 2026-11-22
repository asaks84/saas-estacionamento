// Formatos do sistema (resources/js/lib/formato.js): não dependem do idioma do navegador nem do celular.
// Rodar: npm run test:js
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { moeda, numero, dataHora } from '../../resources/js/lib/formato.js'

test('moeda em pt-BR com espaço comum', () => {
    assert.equal(moeda(1234.5), 'R$ 1.234,50')
    assert.equal(moeda(-5), '-R$ 5,00')
    assert.equal(moeda(null), 'R$ 0,00')
})

test('numero sem zeros sobrando', () => {
    assert.equal(numero(2), '2')
    assert.equal(numero(2.5), '2,5')
    assert.equal(numero(0.033, 2), '0,03')
    assert.equal(numero(1234.567), '1.234,57')
    assert.equal(numero(-12.3), '-12,3')
    assert.equal(numero(-0.001), '0')
    assert.equal(numero(33.333, 1), '33,3')
})

test('dataHora usa a hora que veio do servidor', () => {
    assert.equal(dataHora('2026-11-22T19:02:41-03:00'), '22/11/2026 19:02')
})
