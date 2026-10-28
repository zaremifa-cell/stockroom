<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const KIND = { order: 'Order', transfer_out: 'Transfer out', transfer_in: 'Transfer in', adjustment: 'Adjustment', price_change: 'Price change' };
const rows = ref([]);
const error = ref(null);

onMounted(async () => {
    try { rows.value = await api.movements(); } catch (messages) { error.value = messages.join(' '); }
});
</script>

<template>
    <section class="card">
        <h2>History</h2>
        <p class="muted">Every stock and price change, newest first. Rows are only ever added, never edited.</p>
        <p v-if="error" class="alert" role="alert">{{ error }}</p>
        <p v-else-if="rows.length === 0" class="muted">Nothing yet. Place an order or a transfer to see it here.</p>
        <div v-else class="table-wrap">
            <table>
                <thead><tr><th>When</th><th>What</th><th>Product</th><th>Warehouse</th><th class="num">Change</th><th>Note</th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id">
                        <td class="muted small">{{ new Date(r.created_at).toLocaleString('en-GB') }}</td>
                        <td>{{ KIND[r.kind] ?? r.kind }}</td>
                        <td>{{ r.product }} <div class="muted small"><code>{{ r.sku }}</code></div></td>
                        <td>{{ r.warehouse ?? '—' }}</td>
                        <td class="num" :class="{ neg: r.quantity_change < 0, pos: r.quantity_change > 0 }">{{ r.quantity_change > 0 ? '+' : '' }}{{ r.kind === 'price_change' ? '' : r.quantity_change }}</td>
                        <td class="muted">{{ r.note }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
