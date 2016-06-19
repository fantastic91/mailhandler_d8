<?php

namespace Drupal\mailhandler_d8\Plugin\inmail\Analyzer;

use Drupal\inmail\MIME\MessageInterface;
use Drupal\inmail\Plugin\inmail\Analyzer\AnalyzerBase;
use Drupal\inmail\ProcessorResultInterface;
use Drupal\mailhandler_d8\MailhandlerAnalyzerResult;
use Drupal\mailhandler_d8\MailhandlerAnalyzerResultInterface;
use Drupal\mailhandler_d8\MailhandlerAnalyzerResultSigned;
use Drupal\mailhandler_d8\Plugin\inmail\Handler\MailhandlerNode;

/**
 * A message footer analyzer.
 *
 * @ingroup analyzer
 *
 * @Analyzer(
 *   id = "footer",
 *   label = @Translation("Footer Analyzer")
 * )
 */
class FooterAnalyzer extends AnalyzerBase {

  /**
   * {@inheritdoc}
   */
  public function analyze(MessageInterface $message, ProcessorResultInterface $processor_result) {
    // In order to work with signed messages, get the result of
    // Mailhandler analyzer plugin.
    if (MailhandlerNode::isMessageSigned($processor_result)) {
      /** @var \Drupal\mailhandler_d8\MailhandlerAnalyzerResultSigned $result */
      $result = $processor_result->ensureAnalyzerResult(MailhandlerAnalyzerResultSigned::TOPIC, MailhandlerAnalyzerResultSigned::createFactory());
    }
    else {
      $result = $processor_result->ensureAnalyzerResult(MailhandlerAnalyzerResult::TOPIC, MailhandlerAnalyzerResult::createFactory());
    }

    $this->findFooter($result);
  }

  /**
   * Finds and returns the message footer.
   *
   * @param \Drupal\mailhandler_d8\MailhandlerAnalyzerResultInterface $result
   *   The analyzer result.
   *
   * @return string|null
   *   The message footer or null if not found.
   */
  protected function findFooter(MailhandlerAnalyzerResultInterface $result) {
    // Get a message body.
    $body = $result->getBody();
    $footer = NULL;

    // Per https://tools.ietf.org/html/rfc3676#section-4.3, footer/signature is
    // separated from the message with "-- \n".
    $body_match = preg_split('/\s*[\r\n]--\s+/', $body);

    if (count($body_match) > 1) {
      // Footer represents a string after the last occurrence of "-- \n" regex.
      $footer = end($body_match);

      // Update the analyzed body without footer.
      $footer_key = count($body_match) - 1;
      unset($body_match[$footer_key]);
      $body = nl2br(implode("\n-- \n", $body_match));
      $result->setBody($body);
    }
    // Match "On {day}, {month} {date}, {year} at {hour}:{minute} {AM|PM}".
    elseif (preg_match('/On [A-Za-z]{3}, [A-Za-z]{3} [0-9]{1,2}, 20[0-9]{2} at [0-9]{1,2}:[0-9]{2} (AM|PM).+/', $body, $matches)) {
      $footer_line = reset($matches);
      $footer = strstr($body, $footer_line);
      $result->setBody(nl2br(strstr($body, $footer_line, TRUE)));
    }

    $result->setFooter($footer);
    return $footer;
  }

}
