<?php

namespace Tests\Unit;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use App\Support\ArticleContentDocument;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArticleContentDocumentTest extends TestCase
{
    public function test_it_validates_and_serializes_a_document_with_supported_blocks_and_inline_runs(): void
    {
        $documents = new ArticleContentDocument;
        $document = $this->validDocument();

        $serialized = $documents->serialize($document);
        $decoded = $documents->deserialize($serialized);

        $this->assertSame(1, $decoded['version']);
        $this->assertSame('introduction', $decoded['blocks'][0]['type']);
        $this->assertSame('heading', $decoded['blocks'][1]['type']);
        $this->assertSame(2, $decoded['blocks'][1]['level']);
        $this->assertSame('bullet', $decoded['blocks'][2]['style']);
        $this->assertSame(['bold'], $decoded['blocks'][0]['runs'][0]['marks']);
        $this->assertSame('normal', $decoded['blocks'][0]['runs'][0]['size']);
        $this->assertSame('https://example.test/article', $decoded['blocks'][0]['runs'][1]['href']);
    }

    public function test_it_supports_one_image_block_as_schema_only(): void
    {
        $document = $this->validDocument();
        $document['blocks'][] = [
            'id' => '8a912ca0-1111-4111-8111-111111111111',
            'type' => 'image',
            'media_id' => 42,
            'caption' => 'A descriptive caption.',
        ];

        $serialized = (new ArticleContentDocument)->serialize($document);

        $this->assertStringContainsString('"media_id":42', $serialized);
        $this->assertStringContainsString('"caption":"A descriptive caption."', $serialized);
    }

    public function test_it_rejects_unsupported_versions_and_block_types_including_h1(): void
    {
        $document = $this->validDocument();
        $document['version'] = 2;
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][1]['type'] = 'h1';
        $this->assertDocumentRejected($document);
    }

    public function test_it_rejects_invalid_or_duplicate_block_and_list_item_uuids(): void
    {
        $document = $this->validDocument();
        $document['blocks'][0]['id'] = 'not-a-uuid';
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][2]['items'][0]['id'] = $document['blocks'][0]['id'];
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][2]['items'][0]['id'] = $document['blocks'][2]['items'][1]['id'];
        $this->assertDocumentRejected($document);
    }

    public function test_it_requires_exactly_one_introduction_as_the_first_block(): void
    {
        $document = $this->validDocument();
        $document['blocks'][0]['type'] = 'paragraph';
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][] = $document['blocks'][0];
        $document['blocks'][3]['id'] = '8a912ca0-1111-4111-8111-111111111111';
        $this->assertDocumentRejected($document);
    }

    public function test_h3_requires_a_preceding_h2_and_headings_only_allow_levels_two_and_three(): void
    {
        $document = $this->validDocument();
        $document['blocks'][1]['level'] = 3;
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][1]['level'] = 1;
        $this->assertDocumentRejected($document);
    }

    public function test_it_rejects_unsafe_links_and_invalid_formatting(): void
    {
        $document = $this->validDocument();
        $document['blocks'][0]['runs'][1]['href'] = 'javascript:alert(1)';
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][0]['runs'][0]['marks'] = ['underline'];
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][0]['runs'][0]['size'] = 'huge';
        $this->assertDocumentRejected($document);

        $document = $this->validDocument();
        $document['blocks'][0]['runs'][0]['html'] = '<script>not a content field</script>';
        $this->assertDocumentRejected($document);
    }

    public function test_it_rejects_more_than_one_image_block(): void
    {
        $document = $this->validDocument();
        $image = [
            'id' => '8a912ca0-1111-4111-8111-111111111111',
            'type' => 'image',
            'media_id' => 42,
            'caption' => '',
        ];
        $document['blocks'][] = $image;
        $image['id'] = '9b912ca0-1111-4111-8111-111111111111';
        $document['blocks'][] = $image;

        $this->assertDocumentRejected($document);
    }

    public function test_it_marks_an_article_as_structured_when_storing_a_document(): void
    {
        $article = new Article;

        $documents = new ArticleContentDocument;
        $documents->storeOn($article, $this->validDocument());

        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame($documents->deserialize($documents->serialize($this->validDocument())), $documents->readFrom($article));
    }

    private function validDocument(): array
    {
        return [
            'version' => 1,
            'blocks' => [
                [
                    'id' => '1a912ca0-1111-4111-8111-111111111111',
                    'type' => 'introduction',
                    'runs' => [
                        ['text' => 'Opening ', 'marks' => ['bold']],
                        ['text' => 'with a link', 'href' => 'https://example.test/article', 'size' => 'large'],
                    ],
                ],
                [
                    'id' => '2a912ca0-1111-4111-8111-111111111111',
                    'type' => 'heading',
                    'level' => 2,
                    'runs' => [['text' => 'Main section']],
                ],
                [
                    'id' => '3a912ca0-1111-4111-8111-111111111111',
                    'type' => 'list',
                    'style' => 'bullet',
                    'items' => [
                        ['id' => '4a912ca0-1111-4111-8111-111111111111', 'runs' => [['text' => 'First item']]],
                        ['id' => '5a912ca0-1111-4111-8111-111111111111', 'runs' => [['text' => 'Second item', 'marks' => ['italic']]]],
                    ],
                ],
                [
                    'id' => '6a912ca0-1111-4111-8111-111111111111',
                    'type' => 'heading',
                    'level' => 3,
                    'runs' => [['text' => 'Subsection', 'size' => 'small']],
                ],
                [
                    'id' => '7a912ca0-1111-4111-8111-111111111111',
                    'type' => 'paragraph',
                    'runs' => [['text' => 'Body text.']],
                ],
            ],
        ];
    }

    private function assertDocumentRejected(array $document): void
    {
        try {
            (new ArticleContentDocument)->serialize($document);
            $this->fail('Expected invalid article document to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
    }
}
