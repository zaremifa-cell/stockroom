<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const emit = defineEmits(['changed']);
const file = ref(null);
const result = ref(null);
const errors = ref([]);
const busy = ref(false);
const history = ref([]);

const sample = 'sku,price\nKTL-STL-17,36.90\nBLN-PRO-900,84.50\nMUG-ENM-04,\"9,90\"\nTWL-COT-L,14,90\nXYZ-404,10.00\nPAN-CER-28,abc\n';
const sampleHref = `data:text/csv;charset=utf-8,${encodeURIComponent(sample)}`;

const loadHistory = async () => { try { history.value = await api.imports(); } catch { /* history is optional */ } };
onMounted(loadHistory);

async function upload() {
    busy.value = true;
    errors.value = [];
    try {
        result.value = await api.importPrices(file.value);
        emit('changed');
        loadHistory();
    } catch (messages) {
        errors.value = messages;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="stack">
        <section class="card">
            <h2>Import prices from CSV</h2>
            <p class="muted">Columns <code>sku</code> and <code>price</code> (euros, "12.50" or "12,50"). Valid rows are applied together; every invalid row is listed with its reason. The same file is never applied twice.</p>
            <div class="row">
                <input type="file" accept=".csv,text/csv" aria-label="CSV file" @change="file = $event.target.files[0]" />
                <button :disabled="busy || !file" @click="upload">{{ busy ? 'Importing…' : 'Import' }}</button>
                <a :href="sampleHref" download="sample-prices.csv">Download a sample file</a>
            </div>
            <div v-if="errors.length" class="alert" role="alert"><p v-for="e in errors" :key="e">{{ e }}</p></div>
        </section>

        <section v-if="result" class="card">
            <div class="card-head">
                <h2>{{ result.filename }}</h2>
                <span class="badge" :class="result.already_imported ? 'warn' : 'ok'">{{ result.already_imported ? 'Already imported — no changes' : 'Imported' }}</span>
            </div>
            <p>{{ result.rows_updated }} prices updated · {{ result.rows_unchanged }} unchanged · {{ result.errors.length }} rows skipped</p>
            <ul v-if="result.errors.length" class="list">
                <li v-for="e in result.errors" :key="e.line"><span class="muted">Line {{ e.line }}:</span> {{ e.message }}</li>
            </ul>
        </section>

        <section v-if="history.length" class="card">
            <h2>Earlier imports</h2>
            <ul class="list">
                <li v-for="h in history" :key="h.id">{{ h.filename }} <span class="muted">· {{ h.rows_updated }} updated · {{ h.errors.length }} skipped · {{ new Date(h.created_at).toLocaleString('en-GB') }}</span></li>
            </ul>
        </section>
    </div>
</template>
