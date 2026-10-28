<script setup>
import { ref, watch } from 'vue';
import { api, newKey } from '../api';

const props = defineProps({ warehouses: Array, products: Array });
const emit = defineEmits(['changed']);

const from = ref(null);
const to = ref(null);
const productId = ref(null);
const quantity = ref(10);
const errors = ref([]);
const done = ref(null);
const busy = ref(false);
const key = ref(newKey());

watch(() => props.warehouses, (w) => {
    if (w.length > 1 && !from.value) { from.value = w[1].id; to.value = w[0].id; }
}, { immediate: true });

const stockIn = (warehouseId) => props.products.find((p) => p.id === productId.value)?.stock[warehouseId] ?? 0;
const nameOf = (id) => props.warehouses.find((w) => w.id === id)?.name;

async function submit() {
    busy.value = true;
    errors.value = [];
    done.value = null;
    try {
        await api.transfer({ from_warehouse_id: from.value, to_warehouse_id: to.value, product_id: productId.value, quantity: quantity.value }, key.value);
        done.value = `Moved ${quantity.value} from ${nameOf(from.value)} to ${nameOf(to.value)}.`;
        key.value = newKey();
        emit('changed');
    } catch (messages) {
        errors.value = messages;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="card">
        <h2>Transfer between warehouses</h2>
        <p class="muted">Units leave one warehouse and arrive in the other in a single step, so they can't go missing in between.</p>
        <div class="row">
            <label class="grow">Product
                <select v-model="productId">
                    <option :value="null" disabled>Choose a product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </label>
            <label>From
                <select v-model="from"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ productId ? stockIn(w.id) : '–' }})</option></select>
            </label>
            <label>To
                <select v-model="to"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ productId ? stockIn(w.id) : '–' }})</option></select>
            </label>
            <label class="qty">Quantity<input v-model.number="quantity" type="number" min="1" /></label>
        </div>
        <div class="row end">
            <button :disabled="busy || !productId" @click="submit">{{ busy ? 'Moving…' : 'Transfer' }}</button>
        </div>
        <p v-if="done" class="ok-text" role="status">{{ done }}</p>
        <div v-if="errors.length" class="alert" role="alert"><p v-for="e in errors" :key="e">{{ e }}</p></div>
    </section>
</template>
