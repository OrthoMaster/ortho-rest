<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Giữ lại một ít thẻ định dạng cho nội dung email quán tự soạn: đoạn, xuống dòng,
 * đậm, nghiêng, gạch chân, link, danh sách, tiêu đề nhỏ. Thẻ khác bỏ thẻ giữ chữ,
 * thuộc tính khác bỏ hết; link chỉ nhận http, https, mailto, tel hoặc biến {{...}}.
 */
final class SafeHtml
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'div'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        $root = $document->getElementById('root') ?? $document->documentElement;
        self::walk($root);

        $out = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'head', 'title', 'meta', 'link'], true)) {
                    $node->removeChild($child);

                    continue;
                }

                self::walk($child);

                if (! in_array($tag, self::TAGS, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';

                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $child->removeAttribute($attribute->nodeName);
                }

                if ($tag === 'a' && preg_match('#^(https?://|mailto:|tel:|\{\{[a-z_]+\}\})#i', $href)) {
                    $child->setAttribute('href', $href);
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }
}
