<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\RentalKnowledgeItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeItemController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $items = $rental->knowledgeItems()->orderBy('category')->orderBy('sort_order')->get();

        return view('admin.knowledge-items.index', compact('rental', 'items'));
    }

    public function create(Request $request): View
    {
        $rental = $this->currentRental($request);

        return view('admin.knowledge-items.form', [
            'rental' => $rental,
            'item' => new RentalKnowledgeItem(['category' => RentalKnowledgeItem::CATEGORY_GENERAL, 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);

        $item = $rental->knowledgeItems()->create($this->validated($request));
        $this->audit->record($rental, $request->user(), 'knowledge_item.created', $item, [], $item->title);

        return redirect()->route('admin.knowledge-items.index')->with('status', __('admin.knowledge_created', ['title' => $item->title]));
    }

    public function edit(Request $request, RentalKnowledgeItem $knowledgeItem): View
    {
        $rental = $this->currentRental($request);
        abort_if($knowledgeItem->rental_id !== $rental->id, 404);

        return view('admin.knowledge-items.form', [
            'rental' => $rental,
            'item' => $knowledgeItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, RentalKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($knowledgeItem->rental_id !== $rental->id, 404);

        $knowledgeItem->fill($this->validated($request));
        $changes = $this->audit->dirtyChanges($knowledgeItem);
        $knowledgeItem->save();
        $this->audit->record($rental, $request->user(), 'knowledge_item.updated', $knowledgeItem, $changes, $knowledgeItem->title);

        return redirect()->route('admin.knowledge-items.index')->with('status', __('admin.knowledge_updated', ['title' => $knowledgeItem->title]));
    }

    public function destroy(Request $request, RentalKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($knowledgeItem->rental_id !== $rental->id, 404);

        $knowledgeItem->delete();
        $this->audit->record($rental, $request->user(), 'knowledge_item.deleted', $knowledgeItem, [], $knowledgeItem->title);

        return redirect()->route('admin.knowledge-items.index')->with('status', __('admin.knowledge_deleted'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'translations.en.title' => ['nullable', 'string'],
            'translations.en.body' => ['nullable', 'string'],
            'translations.ja.title' => ['nullable', 'string'],
            'translations.ja.body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'translations' => [
                'en' => ['title' => $data['translations']['en']['title'] ?? null, 'body' => $data['translations']['en']['body'] ?? null],
                'ja' => ['title' => $data['translations']['ja']['title'] ?? null, 'body' => $data['translations']['ja']['body'] ?? null],
            ],
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all(),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function categories(): array
    {
        return [
            RentalKnowledgeItem::CATEGORY_GENERAL,
            RentalKnowledgeItem::CATEGORY_SERVICES,
            RentalKnowledgeItem::CATEGORY_POLICIES,
            RentalKnowledgeItem::CATEGORY_PACKAGES,
            RentalKnowledgeItem::CATEGORY_DELIVERY,
            RentalKnowledgeItem::CATEGORY_FAQ,
        ];
    }
}
