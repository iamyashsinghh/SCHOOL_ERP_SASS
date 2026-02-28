<?php

namespace App\Services\Library;

use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Library\Book;
use App\Models\Tenant\Library\BookCopy;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookService
{
    public function preRequisite(Request $request)
    {
        // $authors = OptionResource::collection(Option::query()
        //     ->byTeam()
        //     ->whereType(OptionType::BOOK_AUTHOR->value)
        //     ->get());

        // $publishers = OptionResource::collection(Option::query()
        //     ->byTeam()
        //     ->whereType(OptionType::BOOK_PUBLISHER->value)
        //     ->get());

        // $languages = OptionResource::collection(Option::query()
        //     ->byTeam()
        //     ->whereType(OptionType::BOOK_LANGUAGE->value)
        //     ->get());

        // $topics = OptionResource::collection(Option::query()
        //     ->byTeam()
        //     ->whereType(OptionType::BOOK_TOPIC->value)
        //     ->get());

        // $categories = OptionResource::collection(Option::query()
        //     ->byTeam()
        //     ->whereType(OptionType::BOOK_CATEGORY->value)
        //     ->get());

        // return compact('authors', 'publishers', 'languages', 'topics', 'categories);

        return [];
    }

    public function getBookCopies(Book $book)
    {
        $date = today()->toDateString();

        return BookCopy::query()
            ->with('condition', 'addition')
            ->leftJoin('book_additions', 'book_additions.id', '=', 'book_copies.book_addition_id')
            ->leftJoin('books', 'books.id', '=', 'book_copies.book_id')
            ->leftJoin(\DB::raw('
            (
                SELECT book_transaction_records.*
                FROM book_transaction_records
                INNER JOIN (
                    SELECT book_copy_id, MAX(id) as latest_record_id
                    FROM book_transaction_records
                    GROUP BY book_copy_id
                ) as latest_record ON book_transaction_records.id = latest_record.latest_record_id WHERE book_transaction_records.return_date IS NULL
            ) as latest_book_transaction_records
            '), 'latest_book_transaction_records.book_copy_id', '=', 'book_copies.id')
            ->leftJoin('book_transactions', 'book_transactions.id', '=', 'latest_book_transaction_records.book_transaction_id')
            ->where('book_copies.book_id', $book->id)
            ->select(
                'book_copies.*',
                'book_transactions.code_number as transaction_code_number',
                'book_transactions.transactionable_type',
                'book_transactions.transactionable_id',
                'book_transactions.issue_date',
                'book_transactions.due_date',
                'book_additions.date as book_addition_date',
                'latest_book_transaction_records.return_date'
            )
            ->get()
            ->map(function ($copy) {
                $issueStatus = [
                    'label' => trans('library.transaction.statuses.available'),
                    'value' => 'available',
                ];
                if ($copy->transactionable_type && ! $copy->return_date) {
                    $issueStatus = [
                        'label' => trans('library.transaction.statuses.issued'),
                        'value' => 'issued',
                    ];
                }

                if (! empty($copy->hold_status?->value)) {
                    $issueStatus = [
                        'label' => trans('library.transaction.statuses.hold'),
                        'value' => 'hold',
                    ];
                }

                $copy->issue_status = $issueStatus;

                return $copy;
            });
    }

    public function create(Request $request): Book
    {
        \DB::beginTransaction();

        $book = Book::forceCreate($this->formatParams($request));

        \DB::commit();

        return $book;
    }

    private function formatParams(Request $request, ?Book $book = null): array
    {
        $formatted = [
            'title' => $request->title,
            'author_id' => $request->author_id,
            'publisher_id' => $request->publisher_id,
            'language_id' => $request->language_id,
            'topic_id' => $request->topic_id,
            'category_id' => $request->category_id,
            'sub_title' => $request->sub_title,
            'subject' => $request->subject,
            'year_published' => $request->year_published,
            'volume' => $request->volume,
            'isbn_number' => $request->isbn_number,
            'call_number' => $request->call_number,
            'edition' => $request->edition,
            'type' => $request->type,
            'page' => (int) $request->page,
            'price' => ! empty($request->price) ? $request->price : 0,
            'summary' => $request->summary,
        ];

        if (! $book) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Book $book): void
    {
        \DB::beginTransaction();

        $book->forceFill($this->formatParams($request, $book))->save();

        \DB::commit();
    }

    public function deletable(Book $book): void
    {
        $bookCopyExists = BookCopy::query()
            ->whereBookId($book->id)
            ->exists();

        if ($bookCopyExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('library.book_addition.book_addition'), 'dependency' => trans('library.book.book')])]);
        }
    }
}
