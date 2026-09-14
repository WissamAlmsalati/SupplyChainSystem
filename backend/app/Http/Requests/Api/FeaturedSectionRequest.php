<?php

namespace App\Http\Requests\Api;

use App\Enums\FeaturedSectionSource;
use App\Services\ProductSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeaturedSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStore = $this->isMethod('post');

        // Preview checks a rule without saving: no title, always rule-based.
        $isPreview = $this->routeIs('featured-sections.preview');
        $isStore = $isStore && ! $isPreview;
        $source = $isPreview
            ? FeaturedSectionSource::Filter->value
            : $this->input('source', $isStore ? FeaturedSectionSource::Manual->value : $this->route('featured_section')?->source?->value);
        $sorts = array_values(array_diff(array_keys(ProductSearch::SORTS), ['relevance']));

        return [
            'title' => [$isStore ? 'required' : 'sometimes', 'string', 'max:150'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'source' => ['sometimes', Rule::enum(FeaturedSectionSource::class)],
            'products_limit' => ['nullable', 'integer', 'min:1', 'max:50'],

            // manual: ordered list; the first id is shown first.
            'product_ids' => [$source === 'manual' && $isStore ? 'required' : 'sometimes', 'array', 'max:50'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],

            // filter: products picked automatically.
            'sort' => [$source === 'filter' && ($isStore || $isPreview) ? 'required' : 'sometimes', 'nullable', Rule::in($sorts)],
            'filters' => ['nullable', 'array'],
            'filters.category_id' => ['nullable', 'array'],
            'filters.category_id.*' => ['integer', 'exists:categories,id'],
            'filters.brand' => ['nullable', 'array'],
            'filters.brand.*' => ['string', 'max:100'],
            'filters.min_price' => ['nullable', 'numeric', 'min:0'],
            'filters.max_price' => array_merge(['nullable', 'numeric', 'min:0'], $this->filled('filters.min_price') ? ['gte:filters.min_price'] : []),
            'filters.in_stock' => ['nullable', 'boolean'],
        ];
    }
}
