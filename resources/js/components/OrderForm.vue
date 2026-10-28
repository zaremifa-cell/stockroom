<script setup>
import { computed, ref, watch } from 'vue';
import { api, euros, newKey } from '../api';

const props = defineProps({ warehouses: Array, products: Array });
const emit = defineEmits(['changed']);

const warehouseId = ref(null);
const customer = ref('Hotel Trimontium');
const lines = ref([{ product_id: null, quantity: 1 }]);
const errors = ref([]);
const busy = ref(false);
const result = ref(null);
// One key per order attempt: a double click or a retry after a network error sends the same key.
const key = ref(newKey());

watch(() => props.warehouses, (w) => { if (!warehouseId.value && w.length) warehouseId.value = w[0].id; }, { immediate: true });

const available = (productId) => props.products.find((p) => p.id === productId)?.stock[warehouseId.value] ?? 0;
const total = computed(() => lines.value.reduce((sum, l) => {
    const p = props.products.find((x) => x.id === l.product_id);
    return sum + (p ? p.price_cents * l.quantity : 0);
}, 0));

async function submit() {
    busy.value = true;
    errors.value = [];
    try {
        result.value = await api.order({
            warehouse_id: warehouseId.value,
            customer: customer.value,
            lines: lines.value.filter((l) => l.product_id),
        }, key.value);
        emit('changed');
    } catch (messages) {
        errors.value = messages;
    } finally {
        busy.value = false;
    }
}

function startNew() {
    result.value = null;
    lines.value = [{ product_id: null, quantity: 1 }];
    key.value = newKey();
}
</script>

<template>
    <section v-if="result" class="card">
        <div class="card-head">
            <div><h2>Order {{ result.reference }}</h2><p class="muted">{{ result.customer }} · {{ euros(result.total_cents) }}</p></div>
            <span class="badge ok">{{ result.replayed ? 'Already placed' : 'Placed' }}</span>
        </div>
        <ul class="list">
            <li v-for="l in result.lines" :key="l.sku">{{ l.quantity }} × {{ l.name }} <span class="muted">· {{ euros(l.unit_price_cents) }}</span></li>
        </ul>
        <div class="row end"><button @click="startNew">New order</button></div>
    </section>

    <section v-else class="card">
        <h2>New order</h2>
        <p class="muted">Stock is taken from one warehouse. If any line can't be filled, nothing is taken.</p>
        <div class="row">
            <label>Warehouse
                <select v-model="warehouseId">
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
            </label>
            <label class="grow">Customer<input v-model="customer" required /></label>
        </div>

        <div v-for="(line, i) in lines" :key="i" class="row line">
            <label class="grow">Product
                <select v-model="line.product_id">
                    <option :value="null" disabled>Choose a product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ available(p.id) }} in stock</option>
                </select>
            </label>
            <label class="qty">Quantity<input v-model.number="line.quantity" type="number" min="1" /></label>
            <button v-if="lines.length > 1" class="ghost" :aria-label="`Remove line ${i + 1}`" @click="lines.splice(i, 1)">Remove</button>
        </div>

        <div class="row end">
            <button class="ghost" @click="lines.push({ product_id: null, quantity: 1 })">Add line</button>
            <span class="muted">Total {{ euros(total) }}</span>
            <button :disabled="busy || !lines.some((l) => l.product_id)" @click="submit">{{ busy ? 'Placing…' : 'Place order' }}</button>
        </div>
        <div v-if="errors.length" class="alert" role="alert"><p v-for="e in errors" :key="e">{{ e }}</p></div>
    </section>
</template>
